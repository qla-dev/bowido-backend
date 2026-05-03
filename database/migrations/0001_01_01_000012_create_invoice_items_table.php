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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('pallet_id')->nullable()->constrained('pallets')->nullOnDelete()->cascadeOnUpdate();
            $table->string('description');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('billed_days');
            $table->decimal('price_per_day', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'pallet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
