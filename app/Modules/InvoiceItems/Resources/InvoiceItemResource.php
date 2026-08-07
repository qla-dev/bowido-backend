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
            'pallet_name' => $this->whenLoaded('pallet', fn (): ?string => $this->pallet?->pallet_name ?: $this->pallet?->reference_code),
            'pallet_qr' => $this->whenLoaded('pallet', fn (): ?string => $this->pallet?->qr_code),
            'description' => $this->description,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'billed_days' => $this->billed_days,
            'quantity' => $this->billed_days,
            'price_per_day' => $this->price_per_day,
            'unit_price' => (float) $this->price_per_day,
            'amount' => $this->amount,
            'total' => (float) $this->amount,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pallet' => new PalletResource($this->whenLoaded('pallet')),
        ];
    }
}
