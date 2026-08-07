<?php

namespace App\Modules\PalletPhotos\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photoStatus = $this->newStatus ?? $this->oldStatus ?? $this->pallet?->currentStatus;

        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet_id,
            'old_status_id' => $this->old_status_id,
            'new_status_id' => $this->new_status_id,
            'client_id' => $this->client_id,
            'service_report_id' => $this->service_report_id,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'type' => $this->type?->value,
            'warehouse_scope' => $this->warehouse_scope,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'expires_at' => $this->expires_at,
            'url' => $this->expires_at?->isFuture()
                ? route('pallet-photos.file', ['palletPhoto' => $this->id])
                : null,
            'created_at' => $this->created_at,
            'status' => $photoStatus ? [
                'id' => $photoStatus->id,
                'name' => $photoStatus->name,
                'slug' => $photoStatus->slug,
            ] : null,
            'pallet' => $this->whenLoaded('pallet', fn (): array => [
                'id' => $this->pallet->id,
                'qr_code' => $this->pallet->qr_code,
                'name' => $this->pallet->pallet_name ?? $this->pallet->reference_code ?? $this->pallet->qr_code,
                'customer' => $this->pallet->user?->customerDetail?->company_name ?? $this->pallet->user?->name,
                'status' => $this->pallet->currentStatus?->name,
            ]),
            'uploader' => $this->whenLoaded('uploadedByUser', fn (): array => [
                'id' => $this->uploadedByUser->id,
                'name' => $this->uploadedByUser->name,
                'role' => $this->uploadedByUser->role?->name,
            ]),
        ];
    }
}
