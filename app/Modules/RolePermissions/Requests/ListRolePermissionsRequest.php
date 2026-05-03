<?php

namespace App\Modules\RolePermissions\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListRolePermissionsRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'module_id' => ['sometimes', 'integer', 'exists:modules,id'],
            'can_list' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_update' => ['sometimes', 'boolean'],
            'can_delete' => ['sometimes', 'boolean'],
        ];
    }
}
