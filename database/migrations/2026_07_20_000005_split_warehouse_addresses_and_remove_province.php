<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            $table->string('warehouse1_street')->nullable()->after('warehouse_scope');
            $table->string('warehouse1_house_number', 64)->nullable()->after('warehouse1_street');
            $table->string('warehouse1_postal_code', 32)->nullable()->after('warehouse1_house_number');
            $table->string('warehouse1_city')->nullable()->after('warehouse1_postal_code');
            $table->string('warehouse2_street')->nullable()->after('warehouse1_city');
            $table->string('warehouse2_house_number', 64)->nullable()->after('warehouse2_street');
            $table->string('warehouse2_postal_code', 32)->nullable()->after('warehouse2_house_number');
            $table->string('warehouse2_city')->nullable()->after('warehouse2_postal_code');
            $table->dropColumn(['province', 'warehouse1', 'warehouse2']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            $table->string('province')->nullable()->after('country');
            $table->text('warehouse1')->nullable()->after('warehouse_scope');
            $table->text('warehouse2')->nullable()->after('warehouse1');
            $table->dropColumn([
                'warehouse1_street',
                'warehouse1_house_number',
                'warehouse1_postal_code',
                'warehouse1_city',
                'warehouse2_street',
                'warehouse2_house_number',
                'warehouse2_postal_code',
                'warehouse2_city',
            ]);
        });
    }
};
