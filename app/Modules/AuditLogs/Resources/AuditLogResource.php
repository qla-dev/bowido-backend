<?php

namespace App\Modules\AuditLogs\Resources;

use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Statuses\Resources\StatusResource;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet_id,
            'made_by_user_id' => $this->made_by_user_id,
            'event_type' => $this->event_type,
            'note' => $this->note,
            'old_status_id' => $this->old_status_id,
            'new_status_id' => $this->new_status_id,
            'old_client_id' => $this->old_client_id,
            'new_client_id' => $this->new_client_id,
            'old_location' => $this->old_location,
            'new_location' => $this->new_location,
            'old_qr_code' => $this->old_qr_code,
            'new_qr_code' => $this->new_qr_code,
            'context' => $this->context,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pallet' => new PalletResource($this->whenLoaded('pallet')),
            'made_by_user' => new UserResource($this->whenLoaded('madeByUser')),
            'old_status' => new StatusResource($this->whenLoaded('oldStatus')),
            'new_status' => new StatusResource($this->whenLoaded('newStatus')),
            'old_client' => new UserResource($this->whenLoaded('oldClient')),
            'new_client' => new UserResource($this->whenLoaded('newClient')),
        ];
    }
}
