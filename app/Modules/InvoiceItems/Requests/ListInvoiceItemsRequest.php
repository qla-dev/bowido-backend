<?php

namespace App\Modules\InvoiceItems\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListInvoiceItemsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
        ];
    }
}
