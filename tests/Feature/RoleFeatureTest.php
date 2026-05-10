<?php

namespace Tests\Feature;

use App\Modules\Modules\Models\Module;
use App\Modules\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_role_with_selected_module_permissions(): void
    {
        $admin = $this->makeUser('admin');
        $usersModule = Module::query()->where('slug', 'users')->firstOrFail();
        $rolesModule = Module::query()->where('slug', 'roles')->firstOrFail();

        $response = $this->actingAs($admin, 'api')->postJson('/api/roles', [
            'name' => 'dispatch_manager',
            'description' => 'Dispatch manager role',
            'role_permissions' => [
                [
                    'module_id' => $usersModule->id,
                    'can_list' => true,
                    'can_view' => true,
                ],
                [
                    'module_id' => $rolesModule->id,
                    'can_list' => true,
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'dispatch_manager')
            ->assertJsonPath('data.module_ids', [$rolesModule->id, $usersModule->id])
            ->assertJsonCount(2, 'data.role_permissions');

        $firstRolePermissionModule = $response->json('data.role_permissions.0.module');
        $responseData = $response->json('data');

        $this->assertIsArray($firstRolePermissionModule);
        $this->assertSame(['id', 'name'], array_keys($firstRolePermissionModule));
        $this->assertIsArray($responseData);
        $this->assertArrayNotHasKey('created_at', $responseData);
        $this->assertArrayNotHasKey('updated_at', $responseData);
        $this->assertStringStartsWith('{"message":"Role created successfully.","data":', $response->getContent());

        $role = Role::query()->where('name', 'dispatch_manager')->firstOrFail();

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'module_id' => $usersModule->id,
            'can_list' => true,
            'can_view' => true,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'module_id' => $rolesModule->id,
            'can_list' => true,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => false,
        ]);
    }

    public function test_admin_can_update_role_module_permissions_by_role_permissions(): void
    {
        $admin = $this->makeUser('admin');
        $role = Role::factory()->create([
            'name' => 'custom_role',
        ]);
        $usersModule = Module::query()->where('slug', 'users')->firstOrFail();
        $rolesModule = Module::query()->where('slug', 'roles')->firstOrFail();

        $role->rolePermissions()->create([
            'module_id' => $usersModule->id,
            'can_list' => true,
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $response = $this->actingAs($admin, 'api')->putJson('/api/roles/'.$role->id, [
            'role_permissions' => [
                [
                    'module_id' => $rolesModule->id,
                    'can_list' => true,
                    'can_view' => true,
                    'can_create' => true,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.module_ids', [$rolesModule->id])
            ->assertJsonCount(1, 'data.role_permissions');

        $updatedRolePermissionModule = $response->json('data.role_permissions.0.module');

        $this->assertIsArray($updatedRolePermissionModule);
        $this->assertSame(['id', 'name'], array_keys($updatedRolePermissionModule));

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'module_id' => $usersModule->id,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'module_id' => $rolesModule->id,
            'can_list' => true,
            'can_view' => true,
            'can_create' => true,
            'can_update' => false,
            'can_delete' => false,
        ]);
    }
}
