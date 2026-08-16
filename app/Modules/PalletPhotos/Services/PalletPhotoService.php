<?php

namespace App\Modules\PalletPhotos\Services;

use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Support\OffsetPaginationResult;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class PalletPhotoService
{
    public function store(
        Pallet $pallet,
        User $actor,
        UploadedFile $image,
        PalletPhotoType $type,
        ?ServiceReport $serviceReport = null,
        ?int $oldStatusId = null,
        ?int $newStatusId = null,
        ?int $clientId = null,
    ): PalletPhoto {
        [$content, $width, $height] = $this->compressForDatabase($image);

        return PalletPhoto::query()->create([
            'pallet_id' => $pallet->id,
            'old_status_id' => $oldStatusId ?? $pallet->current_status_id,
            'new_status_id' => $newStatusId ?? $pallet->current_status_id,
            'client_id' => $clientId ?? $pallet->user_id,
            'service_report_id' => $serviceReport?->id,
            'uploaded_by_user_id' => $actor->id,
            'type' => $type,
            'warehouse_scope' => $this->resolveWarehouseScope($pallet, $oldStatusId, $newStatusId),
            'content' => $content,
            'original_name' => $image->getClientOriginalName(),
            'mime_type' => 'image/webp',
            'size_bytes' => strlen($content),
            'width' => $width,
            'height' => $height,
            'expires_at' => now()->addMonthsNoOverflow((int) config('pallet-photos.retention_months')),
        ]);
    }

    /**
     * Convert every database-backed photo to a valid, small WebP before the
     * insert. This keeps MySQL packets safely below the default limits while
     * retaining a useful image for the secured gallery.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    public function compressForDatabase(UploadedFile $image, string $field = 'image'): array
    {
        $preparedWebp = $this->readPreparedWebp($image);

        if ($preparedWebp !== null) {
            return $preparedWebp;
        }

        try {
            $processed = Image::read($image->getRealPath());
            $processed->scaleDown(width: 1600, height: 1200);

            $content = $this->encodeNearTargetSize($processed);

            return [$content, $processed->width(), $processed->height()];
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                $field => [__('The image could not be processed.')],
            ]);
        }
    }

    /**
     * Browser uploads are already normalized to a small WebP. Store those
     * bytes directly, avoiding quality loss and allowing uploads even when
     * the optional server image driver is unavailable.
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function readPreparedWebp(UploadedFile $image): ?array
    {
        if ($image->getMimeType() !== 'image/webp') {
            return null;
        }

        $content = file_get_contents($image->getRealPath());

        if (! is_string($content) || $content === '' || strlen($content) > 120 * 1024) {
            return null;
        }

        $dimensions = @getimagesize($image->getRealPath());

        if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1])) {
            return null;
        }

        return [$content, (int) $dimensions[0], (int) $dimensions[1]];
    }

    private function encodeNearTargetSize(object $image): string
    {
        $targetBytes = 120 * 1024;

        foreach ([80, 75, 70, 65, 60, 55, 50, 45] as $quality) {
            $encoded = (string) $image->toWebp($quality);

            if (strlen($encoded) <= $targetBytes) {
                return $encoded;
            }
        }

        // An unusually detailed image may still be larger than the target at
        // lower quality. Reduce dimensions gradually, never enlarging it.
        while ($image->width() > 320 || $image->height() > 240) {
            $image->scaleDown(
                width: max(320, (int) floor($image->width() * 0.8)),
                height: max(240, (int) floor($image->height() * 0.8)),
            );
            $encoded = (string) $image->toWebp(45);

            if (strlen($encoded) <= $targetBytes) {
                return $encoded;
            }
        }

        return (string) $image->toWebp(40);
    }

    public function gallery(User $actor, ListQueryData $queryData, array $filters = []): OffsetPaginationResult
    {
        $query = PalletPhoto::query()->with([
            'pallet.user.customerDetail',
            'pallet.currentStatus',
            'oldStatus',
            'newStatus',
            'uploadedByUser.role',
            'serviceReport',
        ])
            // Older delivery images were saved as scan/status photos. Keep
            // those visible, but never mix damage-report evidence into the
            // Delivery Information gallery.
            ->where('type', '!=', PalletPhotoType::DamageReport)
            ->whereHas('pallet.currentStatus', fn (Builder $statusQuery) => $statusQuery->whereIn('slug', ['bij-de-klant', 'ophalen-klant']));

        if (! $actor->isAdmin()) {
            $scope = $actor->modulePermissionScope('image_gallery');
            if ($scope !== 'all') {
                $query->where('warehouse_scope', $scope);
            }
        }

        if (isset($filters['client_id']) && $filters['client_id'] !== '') {
            $clientId = (int) $filters['client_id'];
            $query->where(function (Builder $clientQuery) use ($clientId): void {
                $clientQuery
                    ->where('client_id', $clientId)
                    ->orWhere(function (Builder $legacyClientQuery) use ($clientId): void {
                        $legacyClientQuery
                            ->whereNull('client_id')
                            ->whereHas('pallet', fn (Builder $palletQuery) => $palletQuery->where('user_id', $clientId));
                    });
            });
        }

        if (isset($filters['status_id']) && $filters['status_id'] !== '') {
            $statusId = (int) $filters['status_id'];
            $query->whereHas('pallet', fn (Builder $palletQuery) => $palletQuery->where('current_status_id', $statusId));
        }

        foreach (['pallet_id', 'warehouse_scope', 'uploaded_by_user_id'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.mb_strtolower(trim((string) $filters['search'])).'%';
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->whereHas('pallet', function (Builder $palletQuery) use ($search): void {
                        $palletQuery->where(function (Builder $palletSearchQuery) use ($search): void {
                            $palletSearchQuery
                                ->whereRaw('LOWER(qr_code) LIKE ?', [$search])
                                ->orWhereRaw('LOWER(pallet_name) LIKE ?', [$search])
                                ->orWhereRaw('LOWER(reference_code) LIKE ?', [$search]);
                        });
                    })
                    ->orWhereHas('uploadedByUser', function (Builder $userQuery) use ($search): void {
                        $userQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                    })
                    ->orWhereHas('pallet.user.customerDetail', function (Builder $customerQuery) use ($search): void {
                        $customerQuery
                            ->whereRaw('LOWER(company_name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(kvk) LIKE ?', [$search]);
                    });
            });
        }

        $total = (clone $query)->count();
        $items = $query->latest()->offset($queryData->offset)->limit($queryData->limit)->get();

        return new OffsetPaginationResult($items, $total, $queryData->limit, $queryData->offset);
    }

    public function canAccess(User $actor, PalletPhoto $photo): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        $isTechnician = strtolower((string) $actor->role?->name) === 'technician';
        $isServicePhoto = $photo->service_report_id !== null
            && in_array($photo->type, [PalletPhotoType::DamageReport, PalletPhotoType::ServiceReport], true);

        if ($isTechnician && $isServicePhoto && $actor->hasModulePermission('services', 'view')) {
            return true;
        }

        if ($actor->isCustomer()) {
            return $photo->client_id === $actor->id || $photo->pallet?->user_id === $actor->id;
        }

        $scope = $actor->modulePermissionScope('image_gallery');

        return $actor->hasModulePermission('image_gallery', 'view')
            && ($scope === 'all' || (in_array($scope, ['warehouse_nl', 'warehouse_bih'], true) && $photo->warehouse_scope === $scope));
    }

    private function resolveWarehouseScope(Pallet $pallet, ?int $oldStatusId, ?int $newStatusId): ?string
    {
        $slugs = Status::query()
            ->whereIn('id', array_values(array_filter([$oldStatusId, $newStatusId, $pallet->current_status_id])))
            ->pluck('slug');

        if ($slugs->contains('bowido-nl')) {
            return 'warehouse_nl';
        }

        if ($slugs->contains('bowido-bih')) {
            return 'warehouse_bih';
        }

        $location = strtolower((string) $pallet->current_location);

        if (str_contains($location, 'nl') || str_contains($location, 'netherland')) {
            return 'warehouse_nl';
        }

        if (str_contains($location, 'bih') || str_contains($location, 'bosn')) {
            return 'warehouse_bih';
        }

        return $pallet->user?->customerDetail?->warehouse_scope;
    }

    public function delete(PalletPhoto $photo): void
    {
        // Retain compatibility with files uploaded before photos moved into the database.
        if ($photo->path !== null && $photo->disk !== null) {
            Storage::disk($photo->disk)->delete($photo->path);
        }
        $photo->delete();
    }

    public function pruneExpired(): int
    {
        $deleted = 0;

        PalletPhoto::query()
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($photos) use (&$deleted): void {
                foreach ($photos as $photo) {
                    $this->delete($photo);
                    $deleted++;
                }
            });

        return $deleted;
    }

    public function forCustomer(int $customerUserId, ListQueryData $queryData): OffsetPaginationResult
    {
        $atCustomerStatusId = Status::query()
            ->where('slug', 'bij-de-klant')
            ->value('id');

        if ($atCustomerStatusId === null) {
            return new OffsetPaginationResult(collect(), 0, $queryData->limit, $queryData->offset);
        }

        $query = PalletPhoto::query()
            ->where('client_id', $customerUserId)
            ->where(function ($query) use ($atCustomerStatusId): void {
                $query
                    ->where('old_status_id', $atCustomerStatusId)
                    ->orWhere('new_status_id', $atCustomerStatusId);
            });

        $total = (clone $query)->count();
        $items = $query
            ->latest()
            ->offset($queryData->offset)
            ->limit($queryData->limit)
            ->get();

        return new OffsetPaginationResult($items, $total, $queryData->limit, $queryData->offset);
    }
}
