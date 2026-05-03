<?php

namespace App\Modules\Modules\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $moduleId = $this->route('module')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('modules', 'name')->ignore($moduleId)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('modules', 'slug')->ignore($moduleId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
