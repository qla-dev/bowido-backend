<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('statuses')->where('slug', 'service')->update(['name' => 'Voor reparatie']);
    }

    public function down(): void
    {
        DB::table('statuses')->where('slug', 'service')->update(['name' => 'Service']);
    }
};
