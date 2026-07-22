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
            $table->string('street')->nullable()->after('fixed_phone');
            $table->string('postal_code', 32)->nullable()->after('street');
            $table->string('warehouse_scope', 32)->nullable()->after('postal_code')->index();
        });

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->string('scope', 32)->nullable()->after('can_delete')->index();
        });

        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->string('warehouse_scope', 32)->nullable()->after('type')->index();
        });

        $warehouseStatuses = DB::table('statuses')
            ->whereIn('slug', ['bowido-nl', 'bowido-bih'])
            ->pluck('id', 'slug');

        foreach (['bowido-nl' => 'warehouse_nl', 'bowido-bih' => 'warehouse_bih'] as $slug => $scope) {
            $statusId = $warehouseStatuses->get($slug);
            if ($statusId) {
                DB::table('pallet_photos')
                    ->where(fn ($query) => $query->where('old_status_id', $statusId)->orWhere('new_status_id', $statusId))
                    ->update(['warehouse_scope' => $scope]);
            }
        }

        DB::table('pallet_photos')
            ->whereNotNull('service_report_id')
            ->whereIn('service_report_id', DB::table('service_reports')->select('id')->where('issue_type', 'service'))
            ->update(['type' => 'service_report']);

        Schema::table('pallets', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        $customerStatusIds = DB::table('statuses')
            ->whereIn('slug', ['bij-de-klant', 'ophalen-klant'])
            ->pluck('id');
        DB::table('pallets')->whereNotIn('current_status_id', $customerStatusIds)->update(['user_id' => null]);

        if (Schema::hasColumn('customer_details', 'delivery_address')) {
            DB::table('customer_details')
                ->whereNull('street')
                ->whereNotNull('delivery_address')
                ->update(['street' => DB::raw('delivery_address')]);
        }
    }

    public function down(): void
    {
        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');
        if ($fallbackUserId) {
            DB::table('pallets')->whereNull('user_id')->update(['user_id' => $fallbackUserId]);
            Schema::table('pallets', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }

        Schema::table('pallet_photos', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_scope']);
            $table->dropColumn('warehouse_scope');
        });

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });

        Schema::table('customer_details', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_scope']);
            $table->dropColumn(['street', 'postal_code', 'warehouse_scope']);
        });
    }
};
