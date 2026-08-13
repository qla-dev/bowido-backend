<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('first_time_login')->default(true)->after('password')->index();
        });

        // Accounts that predate this feature have already completed their onboarding.
        DB::table('users')->update(['first_time_login' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['first_time_login']);
            $table->dropColumn('first_time_login');
        });
    }
};
