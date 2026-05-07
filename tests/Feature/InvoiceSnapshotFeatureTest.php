<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomerDetail;
use App\Models\Invoice;
use App\Models\Pallet;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSnapshotFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_snapshot_creates_snapshot_and_prevents_duplicate_period_billing(): void
    {
        Carbon::setTestNow('2026-05-06 10:00:00');

        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');

        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'default_price_per_day' => 10,
            'grace_period_days' => 0,
            'is_active' => true,
        ]);

        $atCustomer = Status::query()->where('slug', 'at_customer')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'qr_code' => 'INV-SNAP-001',
        ]);

        $enteredCustomerBilling = AuditLog::query()->create([
            'pallet_id' => $pallet->id,
            'made_by_user_id' => $admin->id,
            'event_type' => 'created',
            'new_status_id' => $atCustomer->id,
            'new_client_id' => $customer->id,
            'new_qr_code' => $pallet->qr_code,
            'qr_code_version' => 1,
        ]);
        $enteredCustomerBilling->forceFill(['created_at' => Carbon::parse('2026-05-01 08:00:00')])->saveQuietly();

        $this->actingAs($admin, 'api')
            ->postJson('/api/invoices/send-snapshot', [
                'customer_id' => $customer->id,
                'billing_period_start' => '2026-05-01',
                'billing_period_end' => '2026-05-03',
                'mark_as_sent' => true,
                'note' => 'May invoice sent',
            ])->assertCreated()
            ->assertJsonPath('data.billing_period_start', '2026-05-01')
            ->assertJsonPath('data.billing_period_end', '2026-05-03')
            ->assertJsonPath('data.total_amount', '30.00');

        $invoice = Invoice::query()->where('user_id', $customer->id)->firstOrFail();

        $this->assertSame('2026-05-01', $invoice->billing_period_start?->toDateString());
        $this->assertSame('2026-05-03', $invoice->billing_period_end?->toDateString());

        $this->actingAs($admin, 'api')
            ->postJson('/api/invoices/send-snapshot', [
                'customer_id' => $customer->id,
                'billing_period_start' => '2026-05-01',
                'billing_period_end' => '2026-05-03',
                'mark_as_sent' => true,
            ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
