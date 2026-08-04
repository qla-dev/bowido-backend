<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pallets', 'is_no_qr_code')) {
            return;
        }

        $indexName = null;
        foreach (Schema::getIndexes('pallets') as $index) {
            if ($index['columns'] === ['is_no_qr_code'] && ! $index['primary']) {
                $indexName = $index['name'];
                break;
            }
        }

        Schema::table('pallets', function (Blueprint $table) use ($indexName): void {
            if ($indexName !== null) {
                $table->dropIndex($indexName);
            }

            $table->dropColumn('is_no_qr_code');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pallets', 'is_no_qr_code')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table): void {
            $table->boolean('is_no_qr_code')->default(false)->index()->after('is_ghost');
        });
    }
};
