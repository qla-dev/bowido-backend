<?php

namespace App\Modules\ServiceReports\Resources;

use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceReportResource extends JsonResource
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
            'reported_by_user_id' => $this->reported_by_user_id,
            'resolved_by_user_id' => $this->resolved_by_user_id,
            'status' => $this->status,
            'severity' => $this->severity,
            'issue_type' => $this->issue_type,
            'description' => $this->description,
            'resolution_note' => $this->resolution_note,
            'image_path' => $this->image_path,
            'resolved_at' => $this->resolved_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pallet' => new PalletResource($this->whenLoaded('pallet')),
            'reported_by_user' => new UserResource($this->whenLoaded('reportedByUser')),
            'resolved_by_user' => new UserResource($this->whenLoaded('resolvedByUser')),
        ];
    }
}
