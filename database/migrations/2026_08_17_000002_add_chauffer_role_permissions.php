<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $legacyRole = DB::table('roles')->where('name', 'chauffer')->first();
        $driverRole = DB::table('roles')->where('name', 'driver')->first();

        if ($legacyRole && ! $driverRole) {
            DB::table('roles')->where('id', $legacyRole->id)->update([
                'name' => 'driver',
                'description' => 'Transport',
                'updated_at' => $now,
            ]);
            $driverRole = DB::table('roles')->where('id', $legacyRole->id)->first();
        } elseif ($legacyRole && $driverRole) {
            foreach (DB::table('role_permissions')->where('role_id', $legacyRole->id)->get() as $permission) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $driverRole->id)
                    ->where('module_id', $permission->module_id)
                    ->exists();

                if (! $exists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $driverRole->id,
                        'module_id' => $permission->module_id,
                        'permission' => $permission->permission,
                        'can_list' => $permission->can_list,
                        'can_view' => $permission->can_view,
                        'can_create' => $permission->can_create,
                        'can_update' => $permission->can_update,
                        'can_delete' => $permission->can_delete,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('users')->where('role_id', $legacyRole->id)->update(['role_id' => $driverRole->id]);
            DB::table('roles')->where('id', $legacyRole->id)->delete();
        }

        $driverRoleId = $driverRole?->id;

        if (! $driverRoleId) {
            return;
        }

        $permissions = [
            'pallets' => [true, true, false, true, false],
            'customers' => [true, true, false, false, false],
            'statuses' => [true, true, false, false, false],
            'audit_logs' => [true, true, false, false, false],
            'ghost_pallet_reports' => [true, true, true, true, false],
            'services' => [true, true, true, false, false],
        ];

        foreach ($permissions as $moduleSlug => [$canList, $canView, $canCreate, $canUpdate, $canDelete]) {
            $moduleId = DB::table('modules')->where('slug', $moduleSlug)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $driverRoleId,
                'module_id' => $moduleId,
            ], [
                'permission' => false,
                'can_list' => $canList,
                'can_view' => $canView,
                'can_create' => $canCreate,
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // The legacy role is intentionally not recreated.
    }
};
