<?php

namespace App\Modules\Locations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:3', 'max:255'],
            'limit' => ['sometimes', 'integer', 'between:1,10'],
        ];
    }
}
