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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('invoice_number')->unique();
            $table->string('status')->index();
            $table->string('currency', 3)->default('EUR');
            $table->date('billing_period_start')->nullable()->index();
            $table->date('billing_period_end')->nullable()->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->timestamp('issued_at')->nullable()->index();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
