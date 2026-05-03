<?php

namespace App\Modules\ServiceReports\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateServiceReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pallet_id' => ['sometimes', 'integer', 'exists:pallets,id'],
            'status' => ['sometimes', 'in:open,resolved'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'issue_type' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'resolution_note' => ['required_if:status,resolved', 'nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
