<?php

namespace App\Modules\PalletPhotos\Services;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Throwable;

class DeliveryPhotoService
{
    private const DELIVERY_INFORMATION_STATUS_SLUGS = ['bij-de-klant', 'ophalen-klant'];

    public function __construct(private readonly PalletPhotoService $palletPhotos) {}

    public function store(Pallet $pallet, User $actor, UploadedFile $photo): PalletPhoto
    {
        $pallet->loadMissing('currentStatus');

        if (! in_array($pallet->currentStatus?->slug, self::DELIVERY_INFORMATION_STATUS_SLUGS, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photo' => [__('Delivery photos can only be saved for pallets at the customer or ready for customer pickup.')],
            ]);
        }

        [$encodedPhoto, $width, $height] = $this->palletPhotos->compressForDatabase($photo, 'photo');

        try {
            return DB::transaction(function () use ($pallet, $actor, $photo, $encodedPhoto, $width, $height): PalletPhoto {
                // Locking the pallet makes two uploads started at the same time
                // resolve to one 24-hour delivery session.
                $lockedPallet = Pallet::query()->lockForUpdate()->findOrFail($pallet->id);
                $deliveryAudit = AuditLog::query()
                    ->where('pallet_id', $lockedPallet->id)
                    ->where('new_client_id', $lockedPallet->user_id)
                    ->whereHas('newStatus', fn ($query) => $query->where('slug', 'bij-de-klant'))
                    ->whereHas('oldStatus', fn ($query) => $query->whereIn('slug', ['bowido-nl', 'onbekend']))
                    ->latest('created_at')
                    ->latest('id')
                    ->first();

                $deliveryStartedAt = PalletPhoto::query()
                    ->where('pallet_id', $pallet->id)
                    ->where('type', PalletPhotoType::DeliveryPhoto)
                    ->where('delivery_started_at', '>=', now()->subHours(24))
                    ->latest('delivery_started_at')
                    ->value('delivery_started_at') ?? now();

                return PalletPhoto::query()->create([
                'pallet_id' => $lockedPallet->id,
                // The delivery transition belongs to the pallet audit trail.
                // Persist its statuses with every new delivery photo rather
                // than duplicating the pallet's already-updated status.
                'old_status_id' => $deliveryAudit?->old_status_id ?? $lockedPallet->current_status_id,
                'new_status_id' => $deliveryAudit?->new_status_id ?? $lockedPallet->current_status_id,
                'client_id' => $lockedPallet->user_id,
                'uploaded_by_user_id' => $actor->id,
                'type' => PalletPhotoType::DeliveryPhoto,
                'delivery_started_at' => $deliveryStartedAt,
                // A delivery photo is taken after the pallet has left its
                // warehouse. Keep the customer's warehouse assignment so the
                // matching warehouse can still find it in the gallery.
                'warehouse_scope' => $pallet->user?->customerDetail?->warehouse_scope ?? 'warehouse_nl',
                // Delivery photos are retained solely in MySQL. The image
                // gallery streams these bytes using the stored WebP MIME type.
                'disk' => null,
                'path' => null,
                'content' => $encodedPhoto,
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => 'image/webp',
                'size_bytes' => strlen($encodedPhoto),
                'width' => $width,
                'height' => $height,
                'expires_at' => now()->addMonthsNoOverflow((int) config('pallet-photos.retention_months')),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'photo' => [__('The delivery photo could not be saved. Please try again.')],
            ]);
        }
    }
}
