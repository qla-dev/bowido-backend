<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            $table->timestamp('customer_timer_started_at')->nullable()->after('last_status_changed_at');
            $table->timestamp('customer_timer_frozen_at')->nullable()->after('customer_timer_started_at');
        });

        $atCustomerId = DB::table('statuses')->where('slug', 'bij-de-klant')->value('id');
        $pickupId = DB::table('statuses')->where('slug', 'ophalen-klant')->value('id');

        if ($atCustomerId !== null) {
            DB::table('pallets')
                ->where('current_status_id', $atCustomerId)
                ->whereNull('customer_timer_started_at')
                ->update(['customer_timer_started_at' => DB::raw('last_status_changed_at')]);
        }

        // Older pickup records have no historical start field. Freezing them
        // at their recorded pickup timestamp prevents an already requested
        // return from continuing to accrue time after this release.
        if ($pickupId !== null) {
            DB::table('pallets')
                ->where('current_status_id', $pickupId)
                ->whereNull('customer_timer_started_at')
                ->update([
                    'customer_timer_started_at' => DB::raw('last_status_changed_at'),
                    'customer_timer_frozen_at' => DB::raw('last_status_changed_at'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            $table->dropColumn(['customer_timer_started_at', 'customer_timer_frozen_at']);
        });
    }
};
