<?php

namespace App\Modules\CustomerDetails\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerDetailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $customerDetailId = $this->route('customerDetail')?->id;

        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id', Rule::unique('customer_details', 'user_id')->ignore($customerDetailId)],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'kvk' => ['nullable', 'string', 'max:255', Rule::unique('customer_details', 'kvk')->ignore($customerDetailId)],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'default_price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
