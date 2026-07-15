<?php

namespace App\Modules\PalletPhotos\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class PalletPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet_id,
            'old_status_id' => $this->old_status_id,
            'new_status_id' => $this->new_status_id,
            'client_id' => $this->client_id,
            'service_report_id' => $this->service_report_id,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'type' => $this->type?->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'expires_at' => $this->expires_at,
            'url' => $this->expires_at?->isFuture()
                ? URL::temporarySignedRoute('pallet-photos.file', now()->addMinutes(config('pallet-photos.temporary_url_minutes')), ['palletPhoto' => $this->id])
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
