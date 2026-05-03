<?php

namespace App\Modules\Statuses\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:statuses,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:statuses,slug'],
            'description' => ['nullable', 'string'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
