<?php

namespace Tests\Unit;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaAndRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_tables_exist(): void
    {
        foreach ([
            'roles',
            'users',
            'customer_details',
            'statuses',
            'pallets',
            'audit_logs',
            'service_reports',
            'ghost_pallet_reports',
            'invoices',
            'invoice_items',
            'modules',
            'role_permissions',
            'api_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), sprintf('Expected table [%s] to exist.', $table));
        }
    }

    public function test_core_relationships_are_wired_correctly(): void
    {
        $user = $this->makeUser('customer');
        $customerDetail = CustomerDetail::factory()->create(['user_id' => $user->id]);
        $status = Status::query()->where('slug', 'at_customer')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $user->id,
            'current_status_id' => $status->id,
        ]);
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);
        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'pallet_id' => $pallet->id,
        ]);

        $this->assertTrue($user->role()->exists());
        $this->assertTrue($user->customerDetail()->exists());
        $this->assertSame($customerDetail->id, $user->customerDetail->id);
        $this->assertSame($status->id, $pallet->currentStatus->id);
        $this->assertSame($user->id, $invoice->user->id);
        $this->assertSame($invoice->id, $invoiceItem->invoice->id);
        $this->assertSame($pallet->id, $invoiceItem->pallet->id);
    }
}
