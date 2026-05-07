<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pallet;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GhostPalletWorkflowFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_ghost_pair_workflow_pairs_report_and_creates_audit_log(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'at_customer')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
            'qr_code' => 'PAIR-001',
        ]);

        $reportResponse = $this->actingAs($admin, 'api')
            ->postJson('/api/ghost-pallets/report', [
                'customer_id' => $customer->id,
                'quantity' => 1,
                'note' => 'Arrived without QR label',
            ])->assertCreated();

        $ghostReportId = $reportResponse->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/ghost-pallets/'.$ghostReportId.'/pair', [
                'pallet_id' => $pallet->id,
                'qr_code' => $pallet->qr_code,
                'quantity_to_pair' => 1,
                'note' => 'Driver paired during pickup',
            ])->assertOk()
            ->assertJsonPath('data.status', 'paired')
            ->assertJsonPath('data.paired_pallet_id', $pallet->id);

        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => 'ghost_pallet_paired',
        ]);
        $this->assertSame(1, AuditLog::query()->where('event_type', 'ghost_pallet_paired')->count());
    }
}
