<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            $table->boolean('is_for_repair')->default(false)->index()->after('is_ghost');
        });

        $serviceStatusId = DB::table('statuses')->where('slug', 'service')->value('id');
        $bowidoBihStatusId = DB::table('statuses')->where('slug', 'bowido-bih')->value('id');

        if ($serviceStatusId && $bowidoBihStatusId) {
            // Every legacy service pallet keeps its repair state. The former service
            // status is replaced with the normal Bowido BiH location status so no
            // pallet remains linked to the removed status.
            DB::table('pallets')
                ->where('current_status_id', $serviceStatusId)
                ->update([
                    'current_status_id' => $bowidoBihStatusId,
                    'is_for_repair' => true,
                    'updated_at' => now(),
                ]);
        }

        if ($serviceStatusId) {
            DB::table('statuses')->where('id', $serviceStatusId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            $table->dropIndex(['is_for_repair']);
            $table->dropColumn('is_for_repair');
        });
    }
};
