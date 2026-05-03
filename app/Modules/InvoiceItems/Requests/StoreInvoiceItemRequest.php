<?php

namespace App\Modules\InvoiceItems\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreInvoiceItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'description' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'billed_days' => ['required', 'integer', 'min:0'],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
