<?php

namespace Tests\Feature;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Invoices\Mail\BowidoInvoiceMail;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Modules\Models\Module;
use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Statuses\Models\Status;
use Carbon\Carbon;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrackPalCompletionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_and_update_only_their_own_details_through_me_endpoint(): void
    {
        $customer = $this->makeUser('customer');
        $other = $this->makeUser('customer');
        CustomerDetail::factory()->create(['user_id' => $other->id, 'kvk' => 'OTHER-001']);

        $payload = [
            'company_name' => 'My Company', 'kvk' => 'MY-001', 'fixed_phone' => '+31 20 123 4567',
            'billing_email' => 'billing@my-company.test', 'street' => 'Main Street 1', 'postal_code' => '1000 AA',
            'warehouse_scope' => 'warehouse_nl', 'user_id' => $other->id,
        ];

        $this->actingAs($customer, 'api')->putJson('/api/customer-details/me', $payload)
            ->assertOk()->assertJsonPath('data.user_id', $customer->id);
        $this->actingAs($customer, 'api')->getJson('/api/customer-details/me')
            ->assertOk()->assertJsonPath('data.company_name', 'My Company');
        $this->assertDatabaseHas('customer_details', ['user_id' => $customer->id, 'postal_code' => '1000 AA']);
        $this->assertDatabaseHas('customer_details', ['user_id' => $other->id, 'kvk' => 'OTHER-001']);
    }

    public function test_customer_pallet_list_and_detail_are_isolated_and_invalid_assignment_is_cleared(): void
    {
        $admin = $this->makeUser('admin');
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $own = Pallet::factory()->create(['user_id' => $customerA->id, 'current_status_id' => $atCustomer->id]);
        $other = Pallet::factory()->create(['user_id' => $customerB->id, 'current_status_id' => $atCustomer->id]);

        $this->actingAs($customerA, 'api')->getJson('/api/pallets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->actingAs($customerA, 'api')->getJson('/api/pallets/'.$other->id)->assertForbidden();

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$own->id, ['current_status_id' => $warehouse->id, 'user_id' => $customerA->id])
            ->assertOk()->assertJsonPath('data.user_id', null);
    }

    public function test_gallery_scope_and_direct_file_access_are_enforced(): void
    {
        Storage::fake('local');
        $role = $this->role('operator');
        $gallery = Module::query()->where('slug', ModuleKey::ImageGallery->value)->firstOrFail();
        $role->rolePermissions()->updateOrCreate(['module_id' => $gallery->id], ['can_list' => true, 'can_view' => true, 'scope' => 'warehouse_nl']);
        $user = $this->makeUser('operator');
        $admin = $this->makeUser('admin');
        $status = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $pallet = Pallet::factory()->create(['user_id' => null, 'current_status_id' => $status->id]);
        Storage::disk('local')->put('pallet-photos/test.jpg', 'image');
        $photo = PalletPhoto::query()->create([
            'pallet_id' => $pallet->id, 'uploaded_by_user_id' => $admin->id, 'type' => PalletPhotoType::Scan,
            'warehouse_scope' => 'warehouse_bih', 'disk' => 'local', 'path' => 'pallet-photos/test.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user, 'api')->getJson('/api/gallery')->assertOk()->assertJsonCount(0, 'data');
        $url = URL::temporarySignedRoute('pallet-photos.file', now()->addMinute(), ['palletPhoto' => $photo->id]);
        $this->actingAs($user, 'api')->get($url)->assertForbidden();
    }

    public function test_invoice_pdf_and_email_use_server_side_customer_recipient(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create(['user_id' => $customer->id, 'billing_email' => 'colakovic.vedad@qla.dev', 'street' => 'Invoice Road 2']);
        $invoice = Invoice::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin, 'api')->get('/api/invoices/'.$invoice->id.'/preview')
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin, 'api')->postJson('/api/invoices/'.$invoice->id.'/send', ['recipient' => 'attacker@example.test'])
            ->assertOk()->assertJsonPath('data.recipient', 'colakovic.vedad@qla.dev');
        Mail::assertSent(BowidoInvoiceMail::class, 1);
    }

    public function test_leaving_customer_status_sends_an_overdue_pallet_invoice_to_the_billing_recipient(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        Mail::fake();
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'billing_email' => 'colakovic.vedad@qla.dev',
            'grace_period_days' => 2,
            'default_price_per_day' => 2.50,
        ]);
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'qr_code' => 'OVERDUE-001',
            'last_status_changed_at' => now()->subDays(5),
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'current_status_id' => $warehouse->id,
        ])->assertOk()->assertJsonPath('data.user_id', null);

        $invoice = Invoice::query()->with('items')->sole();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('7.50', $invoice->total_amount);
        $this->assertSame(3, $invoice->items->sole()->billed_days);
        $this->assertSame($pallet->id, $invoice->items->sole()->pallet_id);
        Mail::assertSent(BowidoInvoiceMail::class, fn (BowidoInvoiceMail $mail): bool => $mail->hasTo('colakovic.vedad@qla.dev'));
    }

    public function test_dashboard_can_send_an_overdue_pallet_invoice(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        Mail::fake();
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'billing_email' => 'colakovic.vedad@qla.dev',
            'grace_period_days' => 2,
            'default_price_per_day' => 2.50,
        ]);
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(5),
        ]);

        $this->actingAs($admin, 'api')->postJson('/api/pallets/'.$pallet->id.'/overdue-invoice/send')
            ->assertOk()
            ->assertJsonPath('data.recipient', 'colakovic.vedad@qla.dev');

        $invoice = Invoice::query()->with('items')->sole();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame($pallet->id, $invoice->items->sole()->pallet_id);
        Mail::assertSent(BowidoInvoiceMail::class, fn (BowidoInvoiceMail $mail): bool => $mail->hasTo('colakovic.vedad@qla.dev'));
    }

    public function test_service_status_filter_returns_only_service_pallets(): void
    {
        $admin = $this->makeUser('admin');
        $service = Status::query()->where('slug', 'service')->firstOrFail();
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $servicePallet = Pallet::factory()->create(['user_id' => null, 'current_status_id' => $service->id]);
        Pallet::factory()->create(['user_id' => null, 'current_status_id' => $warehouse->id]);

        $this->actingAs($admin, 'api')->getJson('/api/pallets?current_status_id='.$service->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $servicePallet->id);
    }

    public function test_reference_seeding_preserves_existing_role_permission_changes(): void
    {
        $role = $this->role('operator');
        $module = Module::query()->where('slug', ModuleKey::Pallets->value)->firstOrFail();
        $role->rolePermissions()->where('module_id', $module->id)->update(['can_delete' => true]);

        $this->seed(ReferenceDataSeeder::class);

        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'module_id' => $module->id, 'can_delete' => true]);
    }

    public function test_backend_access_changes_immediately_after_permission_assignment(): void
    {
        $operator = $this->makeUser('operator');
        $rolesModule = Module::query()->where('slug', ModuleKey::Roles->value)->firstOrFail();

        $this->actingAs($operator, 'api')->getJson('/api/roles')->assertForbidden();
        $operator->role->rolePermissions()->updateOrCreate(
            ['module_id' => $rolesModule->id],
            ['can_list' => true, 'can_view' => true],
        );
        $operator->unsetRelation('role');

        $this->actingAs($operator, 'api')->getJson('/api/roles')->assertOk();
    }
}
