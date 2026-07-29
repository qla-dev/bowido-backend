<?php

namespace App\Modules\PalletPhotos\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreDeliveryPhotoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:10240',
            ],
        ];
    }
}
