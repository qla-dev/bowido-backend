<?php

namespace App\Modules\CustomerDetails\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertMyCustomerDetailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $detailId = $this->user()?->customerDetail?->id;

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'kvk' => ['required', 'string', 'max:255', Rule::unique('customer_details', 'kvk')->ignore($detailId)],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'fixed_phone' => ['required', 'string', 'max:50'],
            'billing_email' => ['required', 'email', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['required', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'warehouse_scope' => ['nullable', Rule::in(['warehouse_nl', 'warehouse_bih'])],
            'warehouse1_street' => ['nullable', 'string', 'max:255'],
            'warehouse1_house_number' => ['nullable', 'string', 'max:64'],
            'warehouse1_postal_code' => ['nullable', 'string', 'max:32'],
            'warehouse1_city' => ['nullable', 'string', 'max:255'],
            'warehouse2_street' => ['nullable', 'string', 'max:255'],
            'warehouse2_house_number' => ['nullable', 'string', 'max:64'],
            'warehouse2_postal_code' => ['nullable', 'string', 'max:32'],
            'warehouse2_city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
