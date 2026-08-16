<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $atCustomerId = DB::table('statuses')->where('slug', 'bij-de-klant')->value('id');
        $deliveryOriginIds = DB::table('statuses')
            ->whereIn('slug', ['bowido-nl', 'onbekend'])
            ->pluck('id');

        if ($atCustomerId === null || $deliveryOriginIds->isEmpty()) {
            return;
        }

        DB::table('pallet_photos')
            ->select(['id', 'pallet_id', 'client_id'])
            ->where('type', 'scan')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->each(function (object $photo) use ($atCustomerId, $deliveryOriginIds): void {
                $isDeliveryTransition = DB::table('audit_logs')
                    ->where('pallet_id', $photo->pallet_id)
                    ->where('new_client_id', $photo->client_id)
                    ->where('new_status_id', $atCustomerId)
                    ->whereIn('old_status_id', $deliveryOriginIds)
                    ->exists();

                if ($isDeliveryTransition) {
                    DB::table('pallet_photos')
                        ->where('id', $photo->id)
                        ->update(['type' => 'delivery_photo', 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // The original generic scan type cannot be recovered safely.
    }
};
