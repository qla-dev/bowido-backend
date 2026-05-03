<?php

namespace App\Modules\InvoiceItems\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateInvoiceItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'description' => ['sometimes', 'string', 'max:255'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
            'billed_days' => ['sometimes', 'integer', 'min:0'],
            'price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
