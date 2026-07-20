<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            $table->text('warehouse1')->nullable()->after('warehouse_scope');
            $table->text('warehouse2')->nullable()->after('warehouse1');
            $table->dropColumn([
                'billing_address',
                'delivery_address',
                'tax_number',
                'warehouse_postal_code',
                'warehouse_house_number',
                'warehouse_street',
                'warehouse_city',
            ]);
        });

        Schema::table('customer_details', function (Blueprint $table): void {
            $table->decimal('default_price_per_day', 12, 2)->default(2)->change();
            $table->unsignedInteger('grace_period_days')->default(14)->change();
        });

        DB::table('customer_details')->update([
            'default_price_per_day' => 2,
            'grace_period_days' => 14,
        ]);
    }

    public function down(): void
    {
        Schema::table('customer_details', function (Blueprint $table): void {
            $table->text('billing_address')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('warehouse_postal_code', 32)->nullable();
            $table->string('warehouse_house_number', 64)->nullable();
            $table->string('warehouse_street')->nullable();
            $table->string('warehouse_city')->nullable();
            $table->dropColumn(['warehouse1', 'warehouse2']);
        });
    }
};
