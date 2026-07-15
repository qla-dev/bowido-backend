<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->foreignId('old_status_id')->nullable()->after('pallet_id')->constrained('statuses')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('new_status_id')->nullable()->after('old_status_id')->constrained('statuses')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('client_id')->nullable()->after('new_status_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['client_id', 'old_status_id', 'new_status_id'], 'pallet_photos_customer_context_index');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->dropIndex('pallet_photos_customer_context_index');
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('new_status_id');
            $table->dropConstrainedForeignId('old_status_id');
        });
    }
};
