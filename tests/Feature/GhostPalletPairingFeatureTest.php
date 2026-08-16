<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        ])->assertCreated()
            ->assertJsonCount(3, 'data.pallets')
            ->assertJsonPath('data.pallets.0.qr_code', null)
            ->assertJsonPath('data.pallets.0.pallet_name', 'PWNQRC-0001')
            ->assertJsonPath('data.pallets.0.type', 'invullen!');

        $ghostReportId = $ghostReport->json('data.id');

        $this->assertDatabaseHas('ghost_pallet_reports', [
            'id' => $ghostReportId,
            'user_id' => $admin->id,
            'paired_pallet_id' => $ghostReport->json('data.pallets.0.id'),
        ]);

        $this->assertDatabaseCount('pallets', 4);
        $this->assertDatabaseHas('pallets', [
            'ghost_pallet_report_id' => $ghostReportId,
            'pallet_name' => 'PWNQRC-0001',
            'is_ghost' => true,
            'qr_code' => null,
            'type' => 'invullen!',
        ]);

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

    public function test_no_qr_report_creates_named_pallet_and_stores_its_photo_in_the_database(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        $response = $this->actingAs($admin, 'api')->post('/api/ghost_pallet_reports', [
            'user_id' => $customer->id,
            'quantity' => 1,
            'location' => 'Loading dock',
            'image' => UploadedFile::fake()->image('no-qr.jpg', 1000, 700),
            'metadata' => json_encode([
                'entries' => [['location' => 'Loading dock', 'note' => 'Reported from the mobile form']],
            ]),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.pallets.0.pallet_name', 'PWNQRC-0001')
            ->assertJsonPath('data.pallets.0.qr_code', null)
            ->assertJsonPath('data.pallets.0.is_ghost', true)
            ->assertJsonPath('data.pallets.0.type', 'invullen!');

        $pallet = Pallet::query()->where('is_ghost', true)->firstOrFail();
        $photo = PalletPhoto::query()->where('pallet_id', $pallet->id)->firstOrFail();

        $this->assertSame('no_qr_report', $photo->type->value);
        $this->assertNotEmpty($photo->content);
        $this->assertNull($photo->path);
    }

    public function test_no_qr_pallet_can_be_deleted_with_its_report_only_records(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        $report = $this->actingAs($admin, 'api')->post('/api/ghost_pallet_reports', [
            'user_id' => $customer->id,
            'quantity' => 1,
            'location' => 'Loading dock',
            'image' => UploadedFile::fake()->image('no-qr.jpg', 1000, 700),
        ])->assertCreated();

        $palletId = $report->json('data.pallets.0.id');
        $reportId = $report->json('data.id');

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/pallets/'.$palletId)
            ->assertOk();

        $this->assertDatabaseMissing('pallets', ['id' => $palletId]);
        $this->assertDatabaseMissing('pallet_photos', ['pallet_id' => $palletId]);
        $this->assertDatabaseHas('ghost_pallet_reports', [
            'id' => $reportId,
            'paired_pallet_id' => null,
        ]);
    }
}
