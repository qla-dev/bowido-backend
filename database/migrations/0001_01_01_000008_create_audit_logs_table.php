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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('made_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('event_type')->index();
            $table->text('note')->nullable();
            $table->foreignId('old_status_id')->nullable()->constrained('statuses')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('new_status_id')->nullable()->constrained('statuses')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('old_client_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('new_client_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('old_location')->nullable();
            $table->string('new_location')->nullable();
            $table->unsignedInteger('qr_code_version')->nullable();
            $table->string('old_qr_code')->nullable();
            $table->string('new_qr_code')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['pallet_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->unique(['pallet_id', 'qr_code_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
