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
        Schema::table('pallets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pallets', 'pallet_name')) {
                $table->string('pallet_name')->nullable()->after('qr_code');
            }

            if (! Schema::hasColumn('pallets', 'days_at_customer')) {
                $table->unsignedInteger('days_at_customer')->nullable()->after('last_status_changed_at');
            }

            if (! Schema::hasColumn('pallets', 'grace_days')) {
                $table->unsignedInteger('grace_days')->nullable()->after('days_at_customer');
            }

            if (! Schema::hasColumn('pallets', 'overdue_days')) {
                $table->unsignedInteger('overdue_days')->nullable()->after('grace_days');
            }

            if (! Schema::hasColumn('pallets', 'debt_eur')) {
                $table->decimal('debt_eur', 10, 2)->nullable()->after('overdue_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            foreach (['debt_eur', 'overdue_days', 'grace_days', 'days_at_customer', 'pallet_name'] as $column) {
                if (Schema::hasColumn('pallets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
