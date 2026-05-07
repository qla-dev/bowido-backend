<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pallet;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalletLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_and_updating_a_pallet_generates_audit_history(): void
    {
        $admin = $this->makeUser('admin');
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        $warehouse = Status::query()->where('slug', 'bowido_warehouse')->firstOrFail();
        $atCustomer = Status::query()->where('slug', 'at_customer')->firstOrFail();

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/pallets', [
            'user_id' => $customerA->id,
            'current_status_id' => $warehouse->id,
            'qr_code' => ' pal-0001 ',
            'current_location' => 'Inbound Dock',
            'notes' => 'First scan',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.qr_code', 'PAL-0001')
            ->assertJsonPath('data.current_status_id', $warehouse->id);

        $pallet = Pallet::query()->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => AuditLog::EVENT_CREATED,
        ]);

        $updateResponse = $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'user_id' => $customerB->id,
            'current_status_id' => $atCustomer->id,
            'asset_type' => 'pallet',
            'qr_code' => 'pal-0002',
            'reference_code' => 'RF-22',
            'current_location' => 'Customer Yard',
            'notes' => 'Delivered to customer',
            'is_active' => true,
            'is_ghost' => false,
            'metadata' => ['source' => 'test'],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.user_id', $customerB->id)
            ->assertJsonPath('data.current_status_id', $atCustomer->id)
            ->assertJsonPath('data.qr_code', 'PAL-0002');

        $events = AuditLog::query()
            ->where('pallet_id', $pallet->id)
            ->pluck('event_type')
            ->all();

        $this->assertEqualsCanonicalizing([
            AuditLog::EVENT_CREATED,
            AuditLog::EVENT_STATUS_CHANGED,
            AuditLog::EVENT_CLIENT_CHANGED,
            AuditLog::EVENT_LOCATION_CHANGED,
            AuditLog::EVENT_QR_CODE_CHANGED,
        ], $events);

        $this->assertNotNull($pallet->fresh()->last_status_changed_at);
    }
}