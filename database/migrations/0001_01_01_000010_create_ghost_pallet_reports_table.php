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
        Schema::create('ghost_pallet_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('paired_pallet_id')->nullable()->constrained('pallets')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status')->index();
            $table->unsignedInteger('quantity');
            $table->string('location')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('reported_at')->nullable()->index();
            $table->timestamp('paired_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghost_pallet_reports');
    }
};
