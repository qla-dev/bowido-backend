<?php

namespace App\Modules\Users\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

class StoreUserRequest extends ApiFormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        if (is_array($this->input('customer_details'))) {
            Log::warning('Client creation validation failed.', [
                'payload' => $this->except(['password', 'password_confirmation']),
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        parent::failedValidation($validator);
    }

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
            'customer_details.country' => ['nullable', 'string', 'max:255'],
            'customer_details.kvk' => ['nullable', 'string', 'max:255', 'unique:customer_details,kvk'],
            'customer_details.billing_email' => ['nullable', 'email', 'max:255'],
            'customer_details.street' => ['nullable', 'string', 'max:255'],
            'customer_details.house_number' => ['nullable', 'string', 'max:255'],
            'customer_details.postal_code' => ['nullable', 'string', 'max:32'],
            'customer_details.city' => ['nullable', 'string', 'max:255'],
            'customer_details.fixed_phone' => ['nullable', 'string', 'max:255'],
            'customer_details.warehouse1_street' => ['nullable', 'string', 'max:255'],
            'customer_details.warehouse1_house_number' => ['nullable', 'string', 'max:255'],
            'customer_details.warehouse1_postal_code' => ['nullable', 'string', 'max:32'],
            'customer_details.warehouse1_city' => ['nullable', 'string', 'max:255'],
            'customer_details.warehouse2_street' => ['nullable', 'string', 'max:255'],
            'customer_details.warehouse2_house_number' => ['nullable', 'string', 'max:255'],
            'customer_details.warehouse2_postal_code' => ['nullable', 'string', 'max:32'],
            'customer_details.warehouse2_city' => ['nullable', 'string', 'max:255'],
            'customer_details.vat_number' => ['nullable', 'string', 'max:255'],
            'customer_details.default_price_per_day' => ['required_with:customer_details', 'numeric', 'min:0'],
            'customer_details.grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'customer_details.notes' => ['nullable', 'string'],
            'customer_details.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
