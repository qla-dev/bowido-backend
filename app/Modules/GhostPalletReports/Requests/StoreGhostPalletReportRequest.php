<?php

namespace App\Modules\GhostPalletReports\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreGhostPalletReportRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('metadata'))) {
            $metadata = json_decode($this->input('metadata'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['metadata' => $metadata]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }
}
