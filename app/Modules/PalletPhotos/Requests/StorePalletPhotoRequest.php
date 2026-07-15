<?php

namespace App\Modules\PalletPhotos\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StorePalletPhotoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'old_status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'new_status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
