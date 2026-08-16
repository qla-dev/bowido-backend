<?php

namespace App\Modules\Users\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class SendLoginDetailsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }
}
