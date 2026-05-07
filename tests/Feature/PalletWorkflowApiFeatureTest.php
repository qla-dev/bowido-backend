<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pallet;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalletWorkflowApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_pallet_returns_workflow_context_and_counters(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'at_customer')->firstOrFail();

        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
            'qr_code' => 'SCAN-001',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/pallets/scan/'.$pallet->qr_code);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pallet.id', $pallet->id)
            ->assertJsonPath('data.counters.pallet_id', $pallet->id);

        $this->assertContains('mark_ready_for_return', $response->json('data.allowed_actions'));
    }

    public function test_change_status_route_writes_audit_log_with_transition_fields(): void
    {
        $admin = $this->makeUser('admin');
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        $warehouse = Status::query()->where('slug', 'bowido_warehouse')->firstOrFail();
        $atCustomer = Status::query()->where('slug', 'at_customer')->firstOrFail();

        $pallet = Pallet::factory()->create([
            'user_id' => $customerA->id,
            'current_status_id' => $warehouse->id,
            'current_location' => 'Inbound Dock',
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/pallets/'.$pallet->id.'/change-status', [
                'status_id' => $atCustomer->id,
                'customer_id' => $customerB->id,
                'location' => 'Amsterdam, NL',
                'note' => 'Delivered to customer',
            ])->assertOk()
            ->assertJsonPath('data.pallet.current_status_id', $atCustomer->id)
            ->assertJsonPath('data.pallet.user_id', $customerB->id);

        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => AuditLog::EVENT_STATUS_CHANGED,
            'old_status_id' => $warehouse->id,
            'new_status_id' => $atCustomer->id,
            'old_client_id' => $customerA->id,
            'new_client_id' => $customerB->id,
            'old_location' => 'Inbound Dock',
            'new_location' => 'Amsterdam, NL',
        ]);
    }

    public function test_bulk_change_status_updates_multiple_pallets(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $warehouse = Status::query()->where('slug', 'bowido_warehouse')->firstOrFail();
        $transport = Status::query()->where('slug', 'transport')->firstOrFail();

        $palletA = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $warehouse->id,
            'qr_code' => 'BULK-001',
        ]);
        $palletB = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $warehouse->id,
            'qr_code' => 'BULK-002',
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/pallets/bulk-change-status', [
                'qr_codes' => [$palletA->qr_code, $palletB->qr_code],
                'status_id' => $transport->id,
                'location' => 'Truck NL-BiH',
                'note' => 'Bulk loading',
            ])->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->assertDatabaseHas('pallets', [
            'id' => $palletA->id,
            'current_status_id' => $transport->id,
            'current_location' => 'Truck NL-BiH',
        ]);
        $this->assertDatabaseHas('pallets', [
            'id' => $palletB->id,
            'current_status_id' => $transport->id,
            'current_location' => 'Truck NL-BiH',
        ]);
        $this->assertSame(2, AuditLog::query()->where('event_type', AuditLog::EVENT_STATUS_CHANGED)->count());
    }

    public function test_mark_unknown_is_admin_only(): void
    {
        $admin = $this->makeUser('admin');
        $operatorRole = $this->role('operator');
        $this->grantPermissions($operatorRole, ['pallets'], [
            'can_list' => true,
            'can_view' => true,
            'can_update' => true,
        ]);

        $operator = $this->makeUser('operator');
        $customer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'at_customer')->firstOrFail();
        $unknown = Status::query()->where('slug', 'unknown')->firstOrFail();

        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
        ]);

        $this->actingAs($operator, 'api')
            ->postJson('/api/pallets/'.$pallet->id.'/mark-unknown', [
                'reason' => 'Missing after route check',
            ])->assertForbidden();

        $this->actingAs($admin, 'api')
            ->postJson('/api/pallets/'.$pallet->id.'/mark-unknown', [
                'reason' => 'Missing after route check',
            ])->assertOk()
            ->assertJsonPath('data.pallet.current_status_id', $unknown->id);
    }
}