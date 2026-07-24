<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pallets', 'delivery_address')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table): void {
            $table->dropColumn('delivery_address');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pallets', 'delivery_address')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table): void {
            $table->text('delivery_address')->nullable()->after('current_location');
        });
    }
};
