<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'warehouse_operator')->value('id');

        if (! $roleId) {
            return;
        }

        foreach (['audit_logs', 'ghost_pallet_reports'] as $moduleSlug) {
            $moduleId = DB::table('modules')->where('slug', $moduleSlug)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                [
                    'permission' => true,
                    'can_list' => true,
                    'can_view' => true,
                    'can_create' => $moduleSlug === 'ghost_pallet_reports',
                    'can_update' => $moduleSlug === 'ghost_pallet_reports',
                    'can_delete' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        // Permissions may have predated this migration, so rolling back must not revoke them.
    }
};
