<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('service_report_id')->nullable()->constrained('service_reports')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('type')->index();
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['pallet_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_photos');
    }
};
