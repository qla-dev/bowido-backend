<?php

namespace App\Modules\Pallets\Resources;

use App\Modules\Statuses\Resources\StatusResource;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletResource extends JsonResource
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
            'user_id' => $this->user_id,
            'current_status_id' => $this->current_status_id,
            'current_status_name' => $this->whenLoaded('currentStatus', fn (): ?string => $this->currentStatus?->name),
            'client_name' => $this->whenLoaded('user', fn (): ?string => $this->user?->customerDetail?->company_name ?? $this->user?->name),
            'type' => $this->type ?? $this->asset_type,
            'asset_type' => $this->asset_type,
            'qr_code' => $this->qr_code,
            'reference_code' => $this->reference_code,
            'current_location' => $this->current_location,
            'notes' => $this->notes,
            'note' => $this->notes,
            'last_status_changed_at' => $this->last_status_changed_at,
            'is_active' => $this->is_active,
            'is_ghost' => $this->is_ghost,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'current_status' => new StatusResource($this->whenLoaded('currentStatus')),
        ];
    }
}
