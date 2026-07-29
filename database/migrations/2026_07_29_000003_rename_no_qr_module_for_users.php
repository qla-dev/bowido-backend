<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')
            ->where('slug', 'ghost_pallet_reports')
            ->update([
                'name' => 'Pallets without QR codes',
                'description' => 'Reporting and pairing pallets without QR codes.',
            ]);
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('slug', 'ghost_pallet_reports')
            ->update([
                'name' => 'Ghost Pallet Reports',
                'description' => 'Ghost pallet reporting and pairing.',
            ]);
    }
};
