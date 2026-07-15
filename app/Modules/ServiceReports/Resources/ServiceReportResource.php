<?php

namespace App\Modules\ServiceReports\Resources;

use App\Modules\Pallets\Resources\PalletResource;
use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\PalletPhotos\Resources\PalletPhotoResource;
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
            'problem_description' => $this->description,
            'resolution_note' => $this->resolution_note,
            'image_path' => $this->damagePhotoUrl() ?? $this->imageUrl(),
            'photos' => PalletPhotoResource::collection($this->whenLoaded('photos')),
            'resolved_at' => $this->resolved_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pallet' => new PalletResource($this->whenLoaded('pallet')),
            'reported_by_user' => new UserResource($this->whenLoaded('reportedByUser')),
            'resolved_by_user' => new UserResource($this->whenLoaded('resolvedByUser')),
        ];
    }

    private function imageUrl(): ?string
    {
        if (! is_string($this->image_path) || $this->image_path === '') {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        return asset('storage/'.$this->image_path);
    }

    private function damagePhotoUrl(): ?string
    {
        if (! $this->resource->relationLoaded('photos')) {
            return null;
        }

        /** @var PalletPhoto|null $photo */
        $photo = $this->resource->photos
            ->where('type', PalletPhotoType::DamageReport)
            ->sortByDesc('id')
            ->first();

        return $photo instanceof PalletPhoto
            ? (new PalletPhotoResource($photo))->resolve(request())['url']
            : null;
    }
}
