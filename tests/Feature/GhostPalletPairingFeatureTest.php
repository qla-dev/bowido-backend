<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GhostPalletPairingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_pairing_a_ghost_pallet_report_sets_pairing_fields_without_creating_an_audit_log(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);

        $ghostReport = $this->actingAs($admin, 'api')->postJson('/api/ghost_pallet_reports', [
            'user_id' => $customer->id,
            'quantity' => 3,
            'location' => 'Overflow Zone',
            'description' => 'Unidentified stack',
        ])->assertCreated();

        $ghostReportId = $ghostReport->json('data.id');

        $this->actingAs($admin, 'api')->putJson('/api/ghost_pallet_reports/'.$ghostReportId, [
            'user_id' => $customer->id,
            'quantity' => 3,
            'location' => 'Overflow Zone',
            'description' => 'Unidentified stack',
            'notes' => 'Paired during reconciliation',
            'paired_pallet_id' => $pallet->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'paired')
            ->assertJsonPath('data.paired_pallet_id', $pallet->id);

        $pairedGhostReport = GhostPalletReport::query()->findOrFail($ghostReportId);

        $this->assertNotNull($pairedGhostReport->paired_at);
        $this->assertDatabaseMissing('audit_logs', [
            'pallet_id' => $pallet->id,
        ]);
    }
}
