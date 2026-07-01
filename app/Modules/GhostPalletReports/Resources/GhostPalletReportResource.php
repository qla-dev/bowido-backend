<?php

namespace App\Modules\GhostPalletReports\Resources;

use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GhostPalletReportResource extends JsonResource
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
            'paired_pallet_id' => $this->paired_pallet_id,
            'status' => $this->status,
            'quantity' => $this->quantity,
            'location' => $this->location,
            'description' => $this->description,
            'notes' => $this->notes,
            'paired_at' => $this->paired_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'paired_pallet' => new PalletResource($this->whenLoaded('pairedPallet')),
        ];
    }
}
