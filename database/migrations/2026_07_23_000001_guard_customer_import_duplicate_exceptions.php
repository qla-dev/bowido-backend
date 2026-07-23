<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX customer_details_kvk_unique');
            DB::statement(
                "CREATE UNIQUE INDEX customer_details_kvk_unique_guard_unique
                    ON customer_details (lower(replace(replace(replace(trim(kvk), ' ', ''), '.', ''), '-', '')))
                    WHERE kvk IS NOT NULL
                      AND lower(replace(replace(replace(trim(kvk), ' ', ''), '.', ''), '-', ''))
                        NOT IN ('24172907', '27251970')",
            );

            return;
        }

        if (! Schema::hasColumn('customer_details', 'kvk_unique_guard')) {
            DB::statement('ALTER TABLE customer_details DROP INDEX customer_details_kvk_unique');
            DB::statement(
                "ALTER TABLE customer_details
                    ADD COLUMN kvk_unique_guard VARCHAR(255)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(kvk), ' ', ''), '.', ''), '-', ''))
                                IN ('24172907', '27251970')
                            THEN NULL
                            ELSE LOWER(REPLACE(REPLACE(REPLACE(TRIM(kvk), ' ', ''), '.', ''), '-', ''))
                        END
                    ) STORED,
                    ADD UNIQUE INDEX customer_details_kvk_unique_guard_unique (kvk_unique_guard)",
            );
        }

    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX customer_details_kvk_unique_guard_unique');
            DB::statement('CREATE UNIQUE INDEX customer_details_kvk_unique ON customer_details (kvk)');

            return;
        }

        DB::statement('ALTER TABLE customer_details DROP INDEX customer_details_kvk_unique_guard_unique, DROP COLUMN kvk_unique_guard');
        DB::statement('ALTER TABLE customer_details ADD UNIQUE INDEX customer_details_kvk_unique (kvk)');
    }
};
