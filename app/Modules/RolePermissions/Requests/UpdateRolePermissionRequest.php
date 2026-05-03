<?php

namespace App\Modules\RolePermissions\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $rolePermissionId = $this->route('rolePermission')?->id;

        return [
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'module_id' => [
                'sometimes',
                'integer',
                'exists:modules,id',
                Rule::unique('role_permissions')->where(function ($query): void {
                    $query->where('role_id', $this->input('role_id', $this->route('rolePermission')?->role_id));
                })->ignore($rolePermissionId),
            ],
            'can_list' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_update' => ['sometimes', 'boolean'],
            'can_delete' => ['sometimes', 'boolean'],
        ];
    }
}
