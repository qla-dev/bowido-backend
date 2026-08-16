<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The application-wide canonical key for the "Bij de klant" status is
     * hyphenated. Update installations that still carry the underscore form.
     */
    public function up(): void
    {
        $this->renameSlug('bij_de_klant', 'bij-de-klant');
    }

    public function down(): void
    {
        $this->renameSlug('bij-de-klant', 'bij_de_klant');
    }

    private function renameSlug(string $from, string $to): void
    {
        if (
            DB::table('statuses')->where('slug', $to)->exists()
            || ! DB::table('statuses')->where('slug', $from)->exists()
        ) {
            return;
        }

        DB::table('statuses')->where('slug', $from)->update(['slug' => $to]);
    }
};
