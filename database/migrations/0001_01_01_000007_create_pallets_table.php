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
        Schema::create('pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('current_status_id')->constrained('statuses')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('type')->default('pallet')->index();
            $table->string('asset_type')->default('pallet')->index();
            $table->string('qr_code')->unique();
            $table->string('reference_code')->nullable()->index();
            $table->string('current_location')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_status_changed_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_ghost')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'current_status_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pallets');
    }
};
