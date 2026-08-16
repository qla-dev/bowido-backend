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
            ->select(['id', 'pallet_id', 'client_id', 'old_status_id', 'new_status_id'])
            ->whereIn('type', ['delivery_photo', 'scan'])
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->each(function (object $photo) use ($atCustomerId, $deliveryOriginIds): void {
                $audit = DB::table('audit_logs')
                    ->select(['old_status_id', 'new_status_id'])
                    ->where('pallet_id', $photo->pallet_id)
                    ->where('new_client_id', $photo->client_id)
                    ->where('new_status_id', $atCustomerId)
                    ->whereIn('old_status_id', $deliveryOriginIds)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();

                if ($audit === null) {
                    return;
                }

                DB::table('pallet_photos')
                    ->where('id', $photo->id)
                    ->update([
                        'old_status_id' => $audit->old_status_id,
                        'new_status_id' => $audit->new_status_id,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // This is a data correction; the previous duplicated status values
        // cannot be reconstructed safely.
    }
};
