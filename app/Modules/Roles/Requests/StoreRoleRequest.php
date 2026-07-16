<?php

namespace App\Modules\Roles\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreRoleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'module_ids' => ['sometimes', 'array'],
            'module_ids.*' => ['integer', 'distinct', 'exists:modules,id'],
            'role_permissions' => ['sometimes', 'array'],
            'role_permissions.*.module_id' => ['required', 'integer', 'distinct', 'exists:modules,id'],
            'role_permissions.*.can_list' => ['sometimes', 'boolean'],
            'role_permissions.*.can_view' => ['sometimes', 'boolean'],
            'role_permissions.*.can_create' => ['sometimes', 'boolean'],
            'role_permissions.*.can_update' => ['sometimes', 'boolean'],
            'role_permissions.*.can_delete' => ['sometimes', 'boolean'],
            'role_permissions.*.scope' => ['nullable', 'in:all,warehouse_nl,warehouse_bih'],
        ];
    }
}
