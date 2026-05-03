<?php

namespace App\Modules\InvoiceItems\Resources;

use App\Modules\Pallets\Resources\PalletResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'pallet_id' => $this->pallet_id,
            'description' => $this->description,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'billed_days' => $this->billed_days,
            'price_per_day' => $this->price_per_day,
            'amount' => $this->amount,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pallet' => new PalletResource($this->whenLoaded('pallet')),
        ];
    }
}
