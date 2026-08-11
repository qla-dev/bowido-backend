<?php

namespace App\Modules\Invoices\Resources;

use App\Modules\InvoiceItems\Resources\InvoiceItemResource;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'customer_id' => $this->user_id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'issued_at' => $this->issued_at,
            'mailed_at' => $this->mailed_at,
            'issue_date' => $this->issued_at?->toDateString() ?? $this->period_end?->toDateString(),
            'due_at' => $this->due_at,
            'due_date' => $this->due_at?->toDateString(),
            'paid_at' => $this->paid_at,
            'subtotal_amount' => $this->subtotal_amount,
            'total_amount' => $this->total_amount,
            'customer_name' => $this->whenLoaded('user', fn (): ?string => $this->user?->customerDetail?->company_name ?? $this->user?->name),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
