<?php

namespace App\Modules\CustomerDetails\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreCustomerDetailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:customer_details,user_id'],
            'company_name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'kvk' => ['nullable', 'string', 'max:255', 'unique:customer_details,kvk'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'fixed_phone' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'default_price_per_day' => ['required', 'numeric', 'min:0'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
