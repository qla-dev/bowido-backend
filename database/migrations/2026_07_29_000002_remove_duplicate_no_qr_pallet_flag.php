<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            $table->dropIndex(['is_no_qr_code']);
            $table->dropColumn('is_no_qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table): void {
            $table->boolean('is_no_qr_code')->default(false)->index()->after('is_ghost');
        });
    }
};
