<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Statuses\Models\Status;
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
        $received = Status::query()->where('slug', 'received')->firstOrFail();
        $stored = Status::query()->where('slug', 'stored')->firstOrFail();

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/pallets', [
            'user_id' => $customerA->id,
            'current_status_id' => $received->id,
            'qr_code' => ' pal-0001 ',
            'current_location' => 'Inbound Dock',
            'notes' => 'First scan',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.qr_code', 'PAL-0001')
            ->assertJsonPath('data.current_status_id', $received->id);

        $pallet = Pallet::query()->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => AuditEventType::Created->value,
        ]);

        $updateResponse = $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'user_id' => $customerB->id,
            'current_status_id' => $stored->id,
            'asset_type' => 'pallet',
            'qr_code' => 'pal-0002',
            'reference_code' => 'RF-22',
            'current_location' => 'Main Warehouse',
            'notes' => 'Moved to storage',
            'is_active' => true,
            'is_ghost' => false,
            'metadata' => ['source' => 'test'],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.user_id', $customerB->id)
            ->assertJsonPath('data.current_status_id', $stored->id)
            ->assertJsonPath('data.qr_code', 'PAL-0002');

        $events = AuditLog::query()
            ->where('pallet_id', $pallet->id)
            ->pluck('event_type')
            ->all();

        $this->assertEqualsCanonicalizing([
            AuditEventType::Created->value,
            AuditEventType::StatusChanged->value,
            AuditEventType::ClientChanged->value,
            AuditEventType::LocationChanged->value,
            AuditEventType::QrCodeChanged->value,
        ], $events);

        $this->assertNotNull($pallet->fresh()->last_status_changed_at);
    }
}
