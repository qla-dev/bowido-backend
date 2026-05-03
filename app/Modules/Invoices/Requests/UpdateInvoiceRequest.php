<?php

namespace App\Modules\Invoices\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateInvoiceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
            'due_at' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
