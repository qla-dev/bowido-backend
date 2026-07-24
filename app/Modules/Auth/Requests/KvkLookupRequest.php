<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class KvkLookupRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = preg_replace('/[\s.\-\/()]+/', '', trim((string) $this->input('kvk')));

        $this->merge(['kvk' => $normalized]);
    }

    public function rules(): array
    {
        return [
            'kvk' => ['required', 'string', 'regex:/^\d{8}$/'],
        ];
    }
}
