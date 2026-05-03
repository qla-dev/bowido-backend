<?php

namespace Database\Factories;

use App\Modules\Modules\Models\Module;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\Roles\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
{
    protected $model = RolePermission::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'module_id' => Module::factory(),
            'can_list' => true,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => false,
        ];
    }
}
