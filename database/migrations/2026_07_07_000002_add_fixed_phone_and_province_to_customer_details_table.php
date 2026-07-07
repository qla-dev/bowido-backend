<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_details', 'fixed_phone')) {
                $table->string('fixed_phone')->nullable()->after('billing_email');
            }

            if (! Schema::hasColumn('customer_details', 'province')) {
                $table->string('province')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            foreach (['fixed_phone', 'province'] as $column) {
                if (Schema::hasColumn('customer_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
