<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy rows created while foreign-key enforcement was absent.
     *
     * These relationships are nullable in the current schema. A missing parent
     * represents historical context that is no longer available, so detach it
     * before the foreign-key restoration migration runs.
     */
    public function up(): void
    {
        foreach ([
            ['pallets', 'user_id', 'users'],
            ['audit_logs', 'old_status_id', 'statuses'],
            ['audit_logs', 'new_status_id', 'statuses'],
            ['audit_logs', 'old_client_id', 'users'],
            ['audit_logs', 'new_client_id', 'users'],
            ['ghost_pallet_reports', 'user_id', 'users'],
            ['invoices', 'user_id', 'users'],
            ['pallet_photos', 'old_status_id', 'statuses'],
            ['pallet_photos', 'new_status_id', 'statuses'],
            ['pallet_photos', 'client_id', 'users'],
        ] as [$table, $column, $parentTable]) {
            DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, DB::table($parentTable)->select('id'))
                ->update([$column => null]);
        }

        // Tokens are authentication credentials and their owner is mandatory.
        // Removing orphaned tokens matches the cascade policy of api_tokens.user_id.
        DB::table('api_tokens')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // The original parent records are unavailable, so this cleanup is irreversible.
    }
};
