<?php

namespace App\Modules\DeliveryLocations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryLocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet_id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'accuracy_meters' => $this->accuracy_meters !== null ? (float) $this->accuracy_meters : null,
            'formatted_address' => $this->formatted_address,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'provider' => $this->provider,
            'source' => $this->source,
            'confirmed_by_user' => $this->confirmed_by_user,
            'created_by_user_id' => $this->created_by_user_id,
            'captured_at' => $this->captured_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
