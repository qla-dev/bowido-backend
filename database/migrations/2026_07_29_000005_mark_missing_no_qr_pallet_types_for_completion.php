<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pallets')
            ->where('is_ghost', true)
            ->where(function ($query): void {
                $query->whereNull('type')->orWhere('type', '')->orWhere('type', 'pallet');
            })
            ->update(['type' => 'invullen!']);

        DB::table('pallets')
            ->where('is_ghost', true)
            ->where(function ($query): void {
                $query->whereNull('asset_type')->orWhere('asset_type', '')->orWhere('asset_type', 'pallet');
            })
            ->update(['asset_type' => 'invullen!']);
    }

    public function down(): void
    {
        DB::table('pallets')
            ->where('is_ghost', true)
            ->where('type', 'invullen!')
            ->update(['type' => 'pallet']);

        DB::table('pallets')
            ->where('is_ghost', true)
            ->where('asset_type', 'invullen!')
            ->update(['asset_type' => 'pallet']);
    }
};
