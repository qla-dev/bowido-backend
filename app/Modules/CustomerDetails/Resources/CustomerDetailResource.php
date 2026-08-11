<?php

namespace App\Modules\CustomerDetails\Resources;

use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDetailResource extends JsonResource
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
            'company_name' => $this->company_name,
            'name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'country' => $this->country,
            'kvk' => $this->kvk,
            'kvk_number' => $this->kvk,
            'billing_email' => $this->billing_email,
            'fixed_phone' => $this->fixed_phone,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'warehouse_scope' => $this->warehouse_scope,
            'warehouse1_street' => $this->warehouse1_street,
            'warehouse1_house_number' => $this->warehouse1_house_number,
            'warehouse1_postal_code' => $this->warehouse1_postal_code,
            'warehouse1_city' => $this->warehouse1_city,
            'warehouse2_street' => $this->warehouse2_street,
            'warehouse2_house_number' => $this->warehouse2_house_number,
            'warehouse2_postal_code' => $this->warehouse2_postal_code,
            'warehouse2_city' => $this->warehouse2_city,
            'warehouse_addresses' => array_values(array_filter([
                $this->warehouse1_street ? trim("{$this->warehouse1_street} {$this->warehouse1_house_number}, {$this->warehouse1_postal_code} {$this->warehouse1_city}") : null,
                $this->warehouse2_street ? trim("{$this->warehouse2_street} {$this->warehouse2_house_number}, {$this->warehouse2_postal_code} {$this->warehouse2_city}") : null,
            ])),
            'vat_number' => $this->vat_number,
            'default_price_per_day' => $this->default_price_per_day,
            'price_per_day' => (float) $this->default_price_per_day,
            'grace_period_days' => $this->grace_period_days,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
