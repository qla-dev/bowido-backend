<?php

namespace App\Modules\RolePermissions\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreRolePermissionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'module_id' => ['required', 'integer', 'exists:modules,id', Rule::unique('role_permissions')->where(
                fn ($query) => $query->where('role_id', $this->input('role_id'))
            )],
            'can_list' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_update' => ['sometimes', 'boolean'],
            'can_delete' => ['sometimes', 'boolean'],
        ];
    }
}
