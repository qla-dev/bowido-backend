<?php

namespace App\Modules\Invoices\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreInvoiceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_at' => ['nullable', 'date', 'after_or_equal:period_end'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
