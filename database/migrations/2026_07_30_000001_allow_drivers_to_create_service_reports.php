<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driverRoleId = DB::table('roles')->where('name', 'driver')->value('id');
        $servicesModuleId = DB::table('modules')->where('slug', 'services')->value('id');

        if (! $driverRoleId || ! $servicesModuleId) {
            return;
        }

        $permissionQuery = DB::table('role_permissions')
            ->where('role_id', $driverRoleId)
            ->where('module_id', $servicesModuleId);

        if ($permissionQuery->exists()) {
            $permissionQuery->update([
                'permission' => true,
                'can_create' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('role_permissions')->insert([
            'role_id' => $driverRoleId,
            'module_id' => $servicesModuleId,
            'permission' => true,
            'can_list' => true,
            'can_view' => true,
            'can_create' => true,
            'can_update' => false,
            'can_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $driverRoleId = DB::table('roles')->where('name', 'driver')->value('id');
        $servicesModuleId = DB::table('modules')->where('slug', 'services')->value('id');

        if (! $driverRoleId || ! $servicesModuleId) {
            return;
        }

        DB::table('role_permissions')
            ->where('role_id', $driverRoleId)
            ->where('module_id', $servicesModuleId)
            ->update([
                'can_create' => false,
                'updated_at' => now(),
            ]);
    }
};
