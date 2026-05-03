<?php

namespace App\Modules\Users\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:255', 'unique:users,phone_number'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'customer_details' => ['sometimes', 'array'],
            'customer_details.company_name' => ['required_with:customer_details', 'string', 'max:255'],
            'customer_details.billing_email' => ['nullable', 'email', 'max:255'],
            'customer_details.billing_address' => ['nullable', 'string'],
            'customer_details.delivery_address' => ['nullable', 'string'],
            'customer_details.tax_number' => ['nullable', 'string', 'max:255'],
            'customer_details.vat_number' => ['nullable', 'string', 'max:255'],
            'customer_details.default_price_per_day' => ['required_with:customer_details', 'numeric', 'min:0'],
            'customer_details.grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'customer_details.notes' => ['nullable', 'string'],
            'customer_details.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
