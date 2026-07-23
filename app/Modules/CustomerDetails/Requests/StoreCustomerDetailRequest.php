<?php

namespace App\Modules\CustomerDetails\Requests;

use App\Modules\CustomerDetails\Support\CustomerImportExceptions;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreCustomerDetailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $kvkRules = ['nullable', 'string', 'max:255'];
        if (! CustomerImportExceptions::allowsSharedKvk($this->input('kvk'))) {
            $kvkRules[] = 'unique:customer_details,kvk';
        }

        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:customer_details,user_id'],
            'company_name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'kvk' => $kvkRules,
            'billing_email' => ['nullable', 'email', 'max:255'],
            'fixed_phone' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'warehouse_scope' => ['nullable', 'in:warehouse_nl,warehouse_bih'],
            'warehouse1_street' => ['nullable', 'string', 'max:255'],
            'warehouse1_house_number' => ['nullable', 'string', 'max:255'],
            'warehouse1_postal_code' => ['nullable', 'string', 'max:32'],
            'warehouse1_city' => ['nullable', 'string', 'max:255'],
            'warehouse2_street' => ['nullable', 'string', 'max:255'],
            'warehouse2_house_number' => ['nullable', 'string', 'max:255'],
            'warehouse2_postal_code' => ['nullable', 'string', 'max:32'],
            'warehouse2_city' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'default_price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
