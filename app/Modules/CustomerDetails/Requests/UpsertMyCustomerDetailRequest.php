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
            'fixed_phone' => ['required', 'string', 'max:50'],
            'billing_email' => ['required', 'email', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'warehouse_scope' => ['nullable', Rule::in(['warehouse_nl', 'warehouse_bih'])],
        ];
    }
}
