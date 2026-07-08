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
        Schema::create('calendar_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('note_date')->index();
            $table->time('note_time')->nullable();
            $table->string('title')->nullable();
            $table->text('note');
            $table->timestamps();
            $table->index(['note_date', 'note_time']);
        });

        Schema::create('calendar_note_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_note_id')->constrained('calendar_notes')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['calendar_note_id', 'user_id']);
            $table->index(['user_id', 'notified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_note_user');
        Schema::dropIfExists('calendar_notes');
    }
};
