<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGenerationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_generation_calculates_billed_days_and_amounts_from_billable_statuses(): void
    {
        Carbon::setTestNow('2026-04-29 10:00:00');

        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'default_price_per_day' => 10,
            'grace_period_days' => 1,
            'is_active' => true,
        ]);

        $stored = Status::query()->where('slug', 'stored')->firstOrFail();
        $released = Status::query()->where('slug', 'released')->firstOrFail();

        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $released->id,
            'qr_code' => 'INV-PALLET-1',
            'last_status_changed_at' => Carbon::parse('2026-04-04 10:00:00'),
        ]);

        $enteredStorage = AuditLog::query()->create([
            'pallet_id' => $pallet->id,
            'made_by_user_id' => $admin->id,
            'event_type' => 'created',
            'new_status_id' => $stored->id,
            'new_client_id' => $customer->id,
            'new_qr_code' => $pallet->qr_code,
        ]);
        $enteredStorage->forceFill(['created_at' => Carbon::parse('2026-04-01 08:00:00')])->saveQuietly();

        $releasedLog = AuditLog::query()->create([
            'pallet_id' => $pallet->id,
            'made_by_user_id' => $admin->id,
            'event_type' => 'status_changed',
            'old_status_id' => $stored->id,
            'new_status_id' => $released->id,
        ]);
        $releasedLog->forceFill(['created_at' => Carbon::parse('2026-04-04 10:00:00')])->saveQuietly();

        $response = $this->actingAs($admin, 'api')->postJson('/api/invoices', [
            'user_id' => $customer->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-05',
            'due_at' => '2026-04-10',
            'currency' => 'EUR',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.billed_days', 2)
            ->assertJsonPath('data.items.0.amount', '20.00');
    }
}
