<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            $table->decimal('default_price_per_day', 12, 2)->default(2)->change();
            $table->unsignedInteger('grace_period_days')->default(14)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            $table->decimal('default_price_per_day', 12, 2)->default(0)->change();
            $table->unsignedInteger('grace_period_days')->default(0)->change();
        });
    }
};
