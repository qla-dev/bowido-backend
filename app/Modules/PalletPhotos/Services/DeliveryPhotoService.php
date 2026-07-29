<?php

namespace App\Modules\PalletPhotos\Services;

use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Throwable;

class DeliveryPhotoService
{
    public function __construct(private readonly PalletPhotoService $palletPhotos) {}

    public function store(Pallet $pallet, User $actor, UploadedFile $photo): PalletPhoto
    {
        [$encodedPhoto, $width, $height] = $this->palletPhotos->compressForDatabase($photo, 'photo');

        try {
            return PalletPhoto::query()->create([
                'pallet_id' => $pallet->id,
                'old_status_id' => $pallet->current_status_id,
                'new_status_id' => $pallet->current_status_id,
                'client_id' => $pallet->user_id,
                'uploaded_by_user_id' => $actor->id,
                'type' => PalletPhotoType::DeliveryPhoto,
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
        } catch (Throwable $exception) {
            report($exception);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'photo' => [__('The delivery photo could not be saved. Please try again.')],
            ]);
        }
    }
}
