<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $unknownStatusId = DB::table('statuses')->where('slug', 'onbekend')->value('id');

        if ($unknownStatusId === null) {
            return;
        }

        DB::table('pallets')
            ->where('current_status_id', $unknownStatusId)
            ->update(['current_location' => null]);

        DB::table('delivery_locations')
            ->whereIn('pallet_id', DB::table('pallets')
                ->select('id')
                ->where('current_status_id', $unknownStatusId))
            ->delete();
    }

    public function down(): void
    {
        // Location data is intentionally not recoverable after it has been
        // removed from pallets with the Unknown status.
    }
};
