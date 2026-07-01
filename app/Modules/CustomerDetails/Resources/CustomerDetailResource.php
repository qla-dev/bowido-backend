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
            'country' => $this->country,
            'kvk' => $this->kvk,
            'kvk_number' => $this->kvk,
            'billing_email' => $this->billing_email,
            'billing_address' => $this->billing_address,
            'delivery_address' => $this->delivery_address,
            'warehouse_addresses' => array_values(array_filter([
                $this->delivery_address,
                $this->billing_address,
            ])),
            'tax_number' => $this->tax_number,
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
