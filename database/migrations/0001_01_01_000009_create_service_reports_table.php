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
        Schema::create('service_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('reported_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status')->default('open')->index();
            $table->string('severity')->nullable()->index();
            $table->string('issue_type')->nullable()->index();
            $table->text('problem_description')->nullable();
            $table->text('description');
            $table->text('resolution_note')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['pallet_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_reports');
    }
};
