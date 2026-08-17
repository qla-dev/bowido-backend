<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('statuses')
            ->where('slug', 'ophalen-klant')
            ->update(['name' => 'Voor retour']);
    }

    public function down(): void
    {
        DB::table('statuses')
            ->where('slug', 'ophalen-klant')
            ->update(['name' => 'Ophalen klant']);
    }
};
