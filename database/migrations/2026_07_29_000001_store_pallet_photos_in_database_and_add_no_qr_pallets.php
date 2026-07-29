<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's schema builder only exposes BLOB here; photos can be up to
        // 10 MB, so MySQL LONGBLOB is required for the image payload.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE pallet_photos ADD content LONGBLOB NULL AFTER path');
        } else {
            Schema::table('pallet_photos', function (Blueprint $table): void {
                $table->binary('content')->nullable();
            });
        }

        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->string('disk')->nullable()->change();
            $table->string('path')->nullable()->change();
        });

        Schema::table('pallets', function (Blueprint $table): void {
            $table->boolean('is_no_qr_code')->default(false)->index()->after('is_ghost');
            $table->foreignId('ghost_pallet_report_id')
                ->nullable()
                ->after('is_no_qr_code')
                ->constrained('ghost_pallet_reports')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('qr_code')->nullable()->change();
        });

        // Preserve the meaning of the legacy client-created "ghost" pallet records.
        DB::table('pallets')->where('is_ghost', true)->update(['is_no_qr_code' => true]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE pallet_photos DROP COLUMN content');
        } else {
            Schema::table('pallet_photos', function (Blueprint $table): void {
                $table->dropColumn('content');
            });
        }

        Schema::table('pallets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ghost_pallet_report_id');
            $table->dropIndex(['is_no_qr_code']);
            $table->dropColumn('is_no_qr_code');
            $table->string('qr_code')->nullable(false)->change();
        });

        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->string('disk')->nullable(false)->change();
            $table->string('path')->nullable(false)->change();
        });
    }
};
