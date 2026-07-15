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
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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
        $disk = (string) config('pallet-photos.disk');
        $path = $image->store("pallet-photos/{$pallet->id}/{$type->value}", $disk);

        try {
            return PalletPhoto::query()->create([
                'pallet_id' => $pallet->id,
                'old_status_id' => $oldStatusId ?? $pallet->current_status_id,
                'new_status_id' => $newStatusId ?? $pallet->current_status_id,
                'client_id' => $clientId ?? $pallet->user_id,
                'service_report_id' => $serviceReport?->id,
                'uploaded_by_user_id' => $actor->id,
                'type' => $type,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $image->getSize() ?? 0,
                'expires_at' => now()->addMonthsNoOverflow((int) config('pallet-photos.retention_months')),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function delete(PalletPhoto $photo): void
    {
        Storage::disk($photo->disk)->delete($photo->path);
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
