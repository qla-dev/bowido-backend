<?php

namespace App\Modules\Invoices\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListInvoicesRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'invoice_number' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
        ];
    }
}
