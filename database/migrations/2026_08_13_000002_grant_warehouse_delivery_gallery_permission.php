<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'warehouse_operator')->value('id');
        $moduleId = DB::table('modules')->where('slug', 'image_gallery')->value('id');

        if (! $roleId || ! $moduleId) {
            return;
        }

        DB::table('role_permissions')->updateOrInsert(
            ['role_id' => $roleId, 'module_id' => $moduleId],
            [
                'permission' => true,
                'can_list' => true,
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'scope' => 'warehouse_nl',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // Do not revoke an access grant that may have been configured manually.
    }
};
