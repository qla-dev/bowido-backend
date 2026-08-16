<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $customerStatusIds = DB::table('statuses')
            ->whereIn('slug', ['bij-de-klant', 'ophalen-klant'])
            ->pluck('id');

        if ($customerStatusIds->isEmpty()) {
            return;
        }

        // These pre-scope photos are the existing Delivery Information
        // records. They predate warehouse-specific gallery access and belong
        // to the NL warehouse that originally owned this gallery.
        DB::table('pallet_photos')
            ->whereNull('warehouse_scope')
            ->whereIn('pallet_id', DB::table('pallets')
                ->whereIn('current_status_id', $customerStatusIds)
                ->select('id'))
            ->update([
                'warehouse_scope' => 'warehouse_nl',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The original warehouse of legacy photos cannot be reconstructed on rollback.
    }
};
