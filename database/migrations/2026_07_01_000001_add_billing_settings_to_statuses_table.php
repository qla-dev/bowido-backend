<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->unsignedInteger('grace_period_days')->default(0)->after('is_billable');
            $table->decimal('price_per_day', 12, 2)->default(0)->after('grace_period_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table): void {
            $table->dropColumn(['grace_period_days', 'price_per_day']);
        });
    }
};
