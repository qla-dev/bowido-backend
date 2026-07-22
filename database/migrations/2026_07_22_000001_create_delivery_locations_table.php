<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pallet_id')->unique()->constrained('pallets')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->string('formatted_address', 500)->nullable();
            $table->string('street')->nullable();
            $table->string('house_number', 64)->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('provider', 32)->nullable()->index();
            $table->string('source', 32)->default('device_gps')->index();
            $table->boolean('confirmed_by_user')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('captured_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_locations');
    }
};
