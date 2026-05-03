<?php

namespace App\Modules\ServiceReports\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreServiceReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pallet_id' => ['required', 'integer', 'exists:pallets,id'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'issue_type' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
