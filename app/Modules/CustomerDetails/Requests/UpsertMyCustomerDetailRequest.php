<?php

namespace App\Modules\CustomerDetails\Requests;

use App\Modules\CustomerDetails\Support\CustomerImportExceptions;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertMyCustomerDetailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $detailId = $this->user()?->customerDetail?->id;
        $kvkRules = ['required', 'string', 'max:255'];
        if (! CustomerImportExceptions::allowsSharedKvk($this->input('kvk'))) {
            $kvkRules[] = Rule::unique('customer_details', 'kvk')->ignore($detailId);
        }

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'kvk' => $kvkRules,
            'phone_number' => ['nullable', 'string', 'max:255'],
            'fixed_phone' => ['nullable', 'string', 'max:50'],
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
