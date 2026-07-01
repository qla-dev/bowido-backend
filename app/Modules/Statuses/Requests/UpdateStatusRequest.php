<?php

namespace App\Modules\Statuses\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $statusId = $this->route('status')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('statuses', 'name')->ignore($statusId)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('statuses', 'slug')->ignore($statusId)],
            'description' => ['nullable', 'string'],
            'is_billable' => ['sometimes', 'boolean'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
