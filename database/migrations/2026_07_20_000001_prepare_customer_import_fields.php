<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_details', 'house_number')) {
                $table->string('house_number', 64)->nullable()->after('street');
            }

            if (! Schema::hasColumn('customer_details', 'city')) {
                $table->string('city')->nullable()->after('postal_code');
            }

            if (! Schema::hasColumn('customer_details', 'warehouse_postal_code')) {
                $table->string('warehouse_postal_code', 32)->nullable()->after('city');
            }

            if (! Schema::hasColumn('customer_details', 'warehouse_house_number')) {
                $table->string('warehouse_house_number', 64)->nullable()->after('warehouse_postal_code');
            }

            if (! Schema::hasColumn('customer_details', 'warehouse_street')) {
                $table->string('warehouse_street')->nullable()->after('warehouse_house_number');
            }

            if (! Schema::hasColumn('customer_details', 'warehouse_city')) {
                $table->string('warehouse_city')->nullable()->after('warehouse_street');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone_number']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('ghost_pallet_reports', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ghost_pallet_reports', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('phone_number');
        });

        Schema::table('customer_details', function (Blueprint $table): void {
            $table->dropColumn([
                'house_number',
                'city',
                'warehouse_postal_code',
                'warehouse_house_number',
                'warehouse_street',
                'warehouse_city',
            ]);
        });
    }
};
