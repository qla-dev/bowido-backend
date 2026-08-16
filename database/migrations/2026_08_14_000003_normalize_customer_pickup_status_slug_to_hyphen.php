<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keep the customer-pickup status key consistent with the application
     * convention used for the customer lifecycle statuses.
     */
    public function up(): void
    {
        $this->renameSlug('ophalen_klant', 'ophalen-klant');
    }

    public function down(): void
    {
        $this->renameSlug('ophalen-klant', 'ophalen_klant');
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
