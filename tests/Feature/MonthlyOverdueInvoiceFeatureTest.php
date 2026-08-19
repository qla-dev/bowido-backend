<?php

namespace Tests\Feature;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MonthlyOverdueInvoiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_status_changes_add_all_customer_pallets_to_one_monthly_invoice(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        Mail::fake();

        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'grace_period_days' => 2,
            'default_price_per_day' => 2.50,
        ]);
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $customerPickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $firstPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(5),
        ]);
        $secondPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(6),
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$firstPallet->id, [
            'current_status_id' => $customerPickup->id,
        ])->assertOk();

        $this->actingAs($customer, 'api')->putJson('/api/pallets/'.$secondPallet->id.'/client-status', [
            'current_status_id' => $customerPickup->id,
        ])->assertOk();

        $invoice = Invoice::query()->with('items')->sole();

        $this->assertSame($customer->id, $invoice->user_id);
        $this->assertMatchesRegularExpression('/^INV-OVD-20260716-\d{4}$/', $invoice->invoice_number);
        $this->assertSame('2026-07-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-07-31', $invoice->period_end->toDateString());
        $this->assertSame('17.50', $invoice->total_amount);
        $this->assertCount(2, $invoice->items);
        $this->assertEqualsCanonicalizing(
            [$firstPallet->id, $secondPallet->id],
            $invoice->items->pluck('pallet_id')->all(),
        );
        Mail::assertNothingSent();
    }

    public function test_legacy_partial_period_invoice_is_reused_for_another_pallet_in_the_same_month(): void
    {
        Carbon::setTestNow('2026-08-11 10:36:00');

        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'grace_period_days' => 2,
            'default_price_per_day' => 2,
        ]);
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $customerPickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $firstPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(13),
        ]);
        $secondPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(7),
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$firstPallet->id, [
            'current_status_id' => $customerPickup->id,
        ])->assertOk();

        $legacyInvoice = Invoice::query()->sole();
        $legacyInvoice->forceFill([
            'invoice_number' => 'INV-OVD-20260810-0001',
            'period_start' => '2026-07-31',
            'period_end' => '2026-08-10',
            'status' => 'sent',
            'mailed_at' => Carbon::parse('2026-08-10 14:43:00'),
            'created_at' => Carbon::parse('2026-08-10 14:43:00'),
        ])->saveQuietly();
        Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-202608-C001165',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$secondPallet->id, [
            'current_status_id' => $customerPickup->id,
        ])->assertOk();

        $invoice = Invoice::query()->with('items')->sole();

        $this->assertSame($legacyInvoice->id, $invoice->id);
        $this->assertSame('INV-OVD-20260810-0001', $invoice->invoice_number);
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('2026-08-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-08-31', $invoice->period_end->toDateString());
        $this->assertCount(2, $invoice->items);
    }

    public function test_monthly_billing_uses_the_frozen_customer_timer_for_pallets_awaiting_return(): void
    {
        Carbon::setTestNow('2026-08-01 00:01:00');

        try {
            $customer = $this->makeUser('customer');
            CustomerDetail::factory()->create([
                'user_id' => $customer->id,
                'grace_period_days' => 2,
                'default_price_per_day' => 2.50,
            ]);
            $pickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
            $pallet = Pallet::factory()->create([
                'user_id' => $customer->id,
                'current_status_id' => $pickup->id,
                'last_status_changed_at' => Carbon::parse('2026-07-16 14:30:00'),
                'customer_timer_started_at' => Carbon::parse('2026-07-01 09:00:00'),
                'customer_timer_frozen_at' => Carbon::parse('2026-07-16 14:30:00'),
            ]);

            $this->artisan('invoices:generate-previous-month')->assertSuccessful();
            $this->artisan('invoices:generate-previous-month')->assertSuccessful();

            $invoice = Invoice::query()->with('items')->sole();
            $item = $invoice->items->sole();

            $this->assertSame($pallet->id, $item->pallet_id);
            $this->assertSame('2026-07-04', $item->period_start->toDateString());
            $this->assertSame('2026-07-16', $item->period_end->toDateString());
            $this->assertSame(13, $item->billed_days);
            $this->assertSame('32.50', $item->amount);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_data_migration_merges_existing_automatic_monthly_duplicates(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $firstPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);
        $secondPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);
        $legacyInvoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-OVD-20260810-0001',
            'status' => 'sent',
            'period_start' => '2026-07-20',
            'period_end' => '2026-08-10',
            'mailed_at' => Carbon::parse('2026-08-10 14:43:00'),
        ]);
        $legacyInvoice->forceFill(['created_at' => Carbon::parse('2026-08-10 14:43:00')])->saveQuietly();
        $legacyInvoice->items()->create([
            'pallet_id' => $firstPallet->id,
            'description' => 'First pallet',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-10',
            'billed_days' => 10,
            'price_per_day' => 4.80,
            'amount' => 48,
            'metadata' => ['automatic_overdue_invoice' => true],
        ]);
        $duplicate = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-202608-C001165',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);
        $duplicate->items()->create([
            'pallet_id' => $secondPallet->id,
            'description' => 'Second pallet',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-11',
            'billed_days' => 11,
            'price_per_day' => 2,
            'amount' => 22,
            'metadata' => ['automatic_overdue_invoice' => true],
        ]);

        $migration = require database_path('migrations/2026_08_11_000014_consolidate_automatic_monthly_invoices.php');
        $migration->up();

        $invoice = Invoice::query()->with('items')->sole();

        $this->assertSame($legacyInvoice->id, $invoice->id);
        $this->assertSame('INV-OVD-20260810-0001', $invoice->invoice_number);
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('70.00', $invoice->total_amount);
        $this->assertSame('2026-08-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-08-31', $invoice->period_end->toDateString());
        $this->assertCount(2, $invoice->items);
    }

    public function test_previous_month_command_groups_still_overdue_pallets_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');

        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'grace_period_days' => 2,
            'default_price_per_day' => 2.50,
        ]);
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $customerPickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $returnedPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(5),
        ]);

        Pallet::factory()->count(2)->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => Carbon::parse('2026-06-01 10:00:00'),
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$returnedPallet->id, [
            'current_status_id' => $customerPickup->id,
        ])->assertOk();

        Carbon::setTestNow('2026-08-01 00:01:00');

        $this->artisan('invoices:generate-previous-month')->assertSuccessful();
        $this->artisan('invoices:generate-previous-month')->assertSuccessful();

        $invoice = Invoice::query()->with('items')->sole();

        $this->assertSame('2026-07-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-07-31', $invoice->period_end->toDateString());
        $this->assertSame('162.50', $invoice->total_amount);
        $this->assertCount(3, $invoice->items);
        $monthlyItems = $invoice->items->where('pallet_id', '!=', $returnedPallet->id);
        $this->assertCount(2, $monthlyItems);
        $this->assertTrue($monthlyItems->every(
            fn ($item): bool => $item->billed_days === 31
                && $item->period_start->toDateString() === '2026-07-01'
                && $item->period_end->toDateString() === '2026-07-31',
        ));
    }
}
