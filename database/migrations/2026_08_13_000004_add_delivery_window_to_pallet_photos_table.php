<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->timestamp('delivery_started_at')->nullable()->after('type');
            $table->index(['pallet_id', 'type', 'delivery_started_at'], 'pallet_photos_delivery_window_index');
        });

        // Existing delivery photos predate delivery sessions. Treat each
        // existing photo as the start of its own historical session.
        DB::table('pallet_photos')
            ->where('type', 'delivery_photo')
            ->update(['delivery_started_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->dropIndex('pallet_photos_delivery_window_index');
            $table->dropColumn('delivery_started_at');
        });
    }
};
