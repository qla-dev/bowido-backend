<?php

namespace Tests;

use App\Modules\Modules\Models\Module;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    protected function role(string $name): Role
    {
        return Role::query()->where('name', $name)->firstOrFail();
    }

    /**
     * @param  array<string, bool>  $abilities
     * @param  array<int, string>  $moduleSlugs
     */
    protected function grantPermissions(Role $role, array $moduleSlugs, array $abilities = []): void
    {
        $defaults = array_merge([
            'can_list' => false,
            'can_view' => false,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ], $abilities);

        foreach ($moduleSlugs as $moduleSlug) {
            $module = Module::query()->where('slug', $moduleSlug)->firstOrFail();

            RolePermission::query()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'module_id' => $module->id,
                ],
                $defaults,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(string $roleName = 'admin', array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $this->role($roleName)->id,
            ...$attributes,
        ]);
    }
}
