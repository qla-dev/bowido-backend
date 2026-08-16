<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PalletPhotoFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_shows_non_damage_photos_for_pallets_at_the_customer_or_ready_for_pickup(): void
    {
        $admin = $this->makeUser('admin');
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        CustomerDetail::factory()->create(['user_id' => $customerA->id, 'company_name' => 'Customer A']);
        CustomerDetail::factory()->create(['user_id' => $customerB->id, 'company_name' => 'Customer B']);
        $warehouseStatus = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $customerStatus = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pickupStatus = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $palletA = Pallet::factory()->create([
            'user_id' => $customerA->id,
            'current_status_id' => $warehouseStatus->id,
        ]);
        $palletB = Pallet::factory()->create([
            'user_id' => $customerB->id,
            'current_status_id' => $customerStatus->id,
        ]);
        $palletC = Pallet::factory()->create([
            'user_id' => $customerA->id,
            'current_status_id' => $pickupStatus->id,
        ]);
        $photoA = PalletPhoto::query()->create([
            'pallet_id' => $palletA->id,
            'old_status_id' => $warehouseStatus->id,
            'new_status_id' => $warehouseStatus->id,
            'client_id' => null,
            'uploaded_by_user_id' => $admin->id,
            'type' => 'scan',
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);
        $photoB = PalletPhoto::query()->create([
            'pallet_id' => $palletB->id,
            'old_status_id' => $warehouseStatus->id,
            'new_status_id' => $customerStatus->id,
            'client_id' => $customerB->id,
            'uploaded_by_user_id' => $admin->id,
            'type' => 'delivery_photo',
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);
        $photoC = PalletPhoto::query()->create([
            'pallet_id' => $palletC->id,
            'old_status_id' => $customerStatus->id,
            'new_status_id' => $pickupStatus->id,
            'client_id' => $customerA->id,
            'uploaded_by_user_id' => $admin->id,
            'type' => 'delivery_photo',
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);
        $this->actingAs($admin, 'api')
            ->getJson("/api/gallery?client_id={$customerA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photoC->id);

        $this->actingAs($admin, 'api')
            ->getJson("/api/gallery?status_id={$customerStatus->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photoB->id)
            ->assertJsonPath('data.0.status.id', $customerStatus->id);
    }

    public function test_gallery_does_not_show_damage_report_photos(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create(['user_id' => $customer->id, 'current_status_id' => $status->id]);

        PalletPhoto::query()->create([
            'pallet_id' => $pallet->id, 'uploaded_by_user_id' => $admin->id, 'type' => 'damage_report',
            'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);
        $deliveryPhoto = PalletPhoto::query()->create([
            'pallet_id' => $pallet->id, 'uploaded_by_user_id' => $admin->id, 'type' => 'delivery_photo',
            'delivery_started_at' => now(), 'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/gallery')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deliveryPhoto->id);
    }

    public function test_scan_photo_is_stored_in_the_database_without_an_audit_log(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $atCustomerStatus = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $customerDetail = CustomerDetail::factory()->create(['user_id' => $customer->id]);
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);

        $auditLogCount = AuditLog::query()->count();

        $response = $this->actingAs($admin, 'api')->post(
            "/api/pallets/{$pallet->id}/photos",
            [
                'image' => UploadedFile::fake()->image('scan.jpg', 1200, 800),
                'old_status_id' => $status->id,
                'new_status_id' => $atCustomerStatus->id,
                'client_id' => $customer->id,
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.pallet_id', $pallet->id)
            ->assertJsonPath('data.type', 'scan')
            ->assertJsonPath('data.client_id', $customer->id);

        $photo = PalletPhoto::query()->firstOrFail();

        $this->assertSame('scan', $photo->type->value);
        $this->assertNull($photo->service_report_id);
        $this->assertSame($status->id, $photo->old_status_id);
        $this->assertSame($atCustomerStatus->id, $photo->new_status_id);
        $this->assertSame($customer->id, $photo->client_id);
        $this->assertTrue($photo->expires_at->isAfter(now()->addMonths(2)));
        $this->assertNotEmpty($photo->content);
        $this->assertSame('image/webp', $photo->mime_type);
        $this->assertLessThanOrEqual(120 * 1024, $photo->size_bytes);
        $this->assertNull($photo->disk);
        $this->assertNull($photo->path);
        $this->assertSame($auditLogCount, AuditLog::query()->count());

        $this->actingAs($admin, 'api')
            ->get("/api/customer_details/{$customerDetail->id}/pallet-photos")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photo->id);
    }

    public function test_uploader_can_delete_a_pallet_photo(): void
    {
        $admin = $this->makeUser('admin');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create(['current_status_id' => $status->id]);
        $photo = PalletPhoto::query()->create([
            'pallet_id' => $pallet->id,
            'uploaded_by_user_id' => $admin->id,
            'type' => 'scan',
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'api')
            ->deleteJson("/api/pallet-photos/{$photo->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('pallet_photos', ['id' => $photo->id]);
    }

    public function test_status_transition_scan_uses_audit_log_statuses_instead_of_the_updated_pallet_status(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
        ]);
        AuditLog::query()->create([
            'pallet_id' => $pallet->id,
            'made_by_user_id' => $admin->id,
            'event_type' => 'status_changed',
            'old_status_id' => $warehouse->id,
            'new_status_id' => $atCustomer->id,
            'new_client_id' => $customer->id,
        ]);

        $this->actingAs($admin, 'api')
            ->post("/api/pallets/{$pallet->id}/photos", [
                'image' => UploadedFile::fake()->image('transition-scan.jpg'),
                // Simulate the driver uploading after the pallet status has
                // already been updated, when both submitted IDs are current.
                'old_status_id' => $atCustomer->id,
                'new_status_id' => $atCustomer->id,
                'client_id' => $customer->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.old_status_id', $warehouse->id)
            ->assertJsonPath('data.new_status_id', $atCustomer->id);

        $this->assertDatabaseHas('pallet_photos', [
            'pallet_id' => $pallet->id,
            'old_status_id' => $warehouse->id,
            'new_status_id' => $atCustomer->id,
        ]);
    }

    public function test_damage_report_photo_is_stored_with_the_damage_type_without_an_audit_log(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);

        $auditLogCount = AuditLog::query()->count();

        $response = $this->actingAs($admin, 'api')->post(
            '/api/service_reports',
            [
                'pallet_id' => $pallet->id,
                'description' => 'Cracked corner block.',
                'image' => UploadedFile::fake()->image('damage.webp', 1200, 800),
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.photos.0.type', 'damage_report');

        $photo = PalletPhoto::query()->firstOrFail();

        $this->assertSame('damage_report', $photo->type->value);
        $this->assertNotNull($photo->service_report_id);
        $this->assertNotEmpty($photo->content);
        $this->assertSame('image/webp', $photo->mime_type);
        $this->assertLessThanOrEqual(120 * 1024, $photo->size_bytes);
        $this->assertNull($photo->disk);
        $this->assertNull($photo->path);
        $this->assertSame($auditLogCount, AuditLog::query()->count());
    }

    public function test_driver_can_create_a_damage_report_with_description_and_photo(): void
    {
        $driver = $this->makeUser('driver');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);

        $response = $this->actingAs($driver, 'api')->post(
            '/api/service_reports',
            [
                'pallet_id' => $pallet->id,
                'severity' => 'medium',
                'issue_type' => 'damage',
                'description' => 'Driver found a cracked support during delivery.',
                'image' => UploadedFile::fake()->image('driver-damage.jpg', 1200, 800),
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.pallet_id', $pallet->id)
            ->assertJsonPath('data.reported_by_user_id', $driver->id)
            ->assertJsonPath('data.problem_description', 'Driver found a cracked support during delivery.')
            ->assertJsonPath('data.photos.0.type', 'damage_report');

        $this->assertDatabaseHas('service_reports', [
            'pallet_id' => $pallet->id,
            'reported_by_user_id' => $driver->id,
            'status' => 'open',
            'issue_type' => 'damage',
            'description' => 'Driver found a cracked support during delivery.',
        ]);
        $this->assertDatabaseHas('pallet_photos', [
            'pallet_id' => $pallet->id,
            'uploaded_by_user_id' => $driver->id,
            'type' => 'damage_report',
        ]);
    }

    public function test_damage_report_can_store_multiple_photos(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);

        $this->actingAs($admin, 'api')
            ->post('/api/service_reports', [
                'pallet_id' => $pallet->id,
                'description' => 'Damage with more than one photo.',
                'images' => [
                    UploadedFile::fake()->image('damage-one.jpg', 1200, 800),
                    UploadedFile::fake()->image('damage-two.jpg', 1200, 800),
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.photos');

        $this->assertDatabaseCount('pallet_photos', 2);
    }

    public function test_driver_is_authorized_to_create_a_damage_report(): void
    {
        $driver = $this->makeUser('driver');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);

        $this->actingAs($driver, 'api')
            ->post('/api/service_reports', [
                'pallet_id' => $pallet->id,
                'severity' => 'medium',
                'issue_type' => 'damage',
                'description' => 'Driver found damage during delivery.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.reported_by_user_id', $driver->id)
            ->assertJsonPath('data.problem_description', 'Driver found damage during delivery.');
    }

    public function test_delivery_photo_is_reencoded_to_webp_in_the_database_and_can_be_opened_from_the_gallery(): void
    {
        $admin = $this->makeUser('admin');
        $warehouse = $this->makeUser('warehouse_operator');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $warehouseStatus = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ]);
        AuditLog::query()->create([
            'pallet_id' => $pallet->id,
            'made_by_user_id' => $admin->id,
            'event_type' => 'status_changed',
            'old_status_id' => $warehouseStatus->id,
            'new_status_id' => $status->id,
            'new_client_id' => $customer->id,
        ]);

        $response = $this->actingAs($admin, 'api')->post(
            "/api/pallets/{$pallet->id}/delivery-photo",
            ['photo' => UploadedFile::fake()->image('delivery.png', 2000, 1500)],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'delivery_photo')
            ->assertJsonPath('data.mime_type', 'image/webp')
            ->assertJsonPath('data.width', 1600)
            ->assertJsonPath('data.height', 1200)
            ->assertJsonPath('data.old_status_id', $warehouseStatus->id)
            ->assertJsonPath('data.new_status_id', $status->id);

        $photo = PalletPhoto::query()->firstOrFail();

        $this->assertSame('delivery_photo', $photo->type->value);
        $this->assertSame($warehouseStatus->id, $photo->old_status_id);
        $this->assertSame($status->id, $photo->new_status_id);
        $this->assertNull($photo->disk);
        $this->assertNull($photo->path);
        $this->assertSame('warehouse_nl', $photo->warehouse_scope);
        $this->assertNotEmpty($photo->content);
        $this->assertLessThanOrEqual(120 * 1024, $photo->size_bytes);
        $encodedPhoto = $photo->content;
        $this->assertSame('RIFF', substr($encodedPhoto, 0, 4));
        $this->assertSame('WEBP', substr($encodedPhoto, 8, 4));

        $url = (string) $response->json('data.url');
        $parsed = parse_url($url);
        $signedPath = ($parsed['path'] ?? '').(isset($parsed['query']) ? '?'.$parsed['query'] : '');

        $this->actingAs($admin, 'api')
            ->get($signedPath)
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');

        $this->actingAs($warehouse, 'api')
            ->getJson('/api/gallery')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photo->id);
    }

    public function test_delivery_photos_within_24_hours_share_a_delivery_start_time(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create(['user_id' => $customer->id, 'current_status_id' => $status->id]);
        $deliveryStart = now()->subHours(2)->startOfSecond();

        PalletPhoto::query()->create([
            'pallet_id' => $pallet->id,
            'uploaded_by_user_id' => $admin->id,
            'type' => 'delivery_photo',
            'delivery_started_at' => $deliveryStart,
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'api')
            ->post("/api/pallets/{$pallet->id}/delivery-photo", ['photo' => UploadedFile::fake()->image('second-delivery.jpg')])
            ->assertCreated();

        $secondPhoto = PalletPhoto::query()->latest('id')->firstOrFail();

        $this->assertTrue($secondPhoto->delivery_started_at->equalTo($deliveryStart));
    }

    public function test_delivery_photo_after_24_hours_starts_a_new_delivery_window(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create(['user_id' => $customer->id, 'current_status_id' => $status->id]);
        $oldDeliveryStart = now()->subDays(2)->startOfSecond();

        PalletPhoto::query()->create([
            'pallet_id' => $pallet->id,
            'uploaded_by_user_id' => $admin->id,
            'type' => 'delivery_photo',
            'delivery_started_at' => $oldDeliveryStart,
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'api')
            ->post("/api/pallets/{$pallet->id}/delivery-photo", ['photo' => UploadedFile::fake()->image('new-delivery.jpg')])
            ->assertCreated();

        $newPhoto = PalletPhoto::query()->latest('id')->firstOrFail();

        $this->assertTrue($newPhoto->delivery_started_at->isAfter($oldDeliveryStart->addHours(24)));
    }

    public function test_delivery_photo_can_only_be_saved_for_a_pallet_at_the_customer_or_ready_for_pickup(): void
    {
        $admin = $this->makeUser('admin');
        $warehouseStatus = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $pallet = Pallet::factory()->create(['current_status_id' => $warehouseStatus->id]);

        $this->actingAs($admin, 'api')
            ->post("/api/pallets/{$pallet->id}/delivery-photo", [
                'photo' => UploadedFile::fake()->image('delivery.png'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseMissing('pallet_photos', ['pallet_id' => $pallet->id]);
    }

    public function test_customer_gallery_only_shows_delivery_photos_for_their_current_delivery_pallets(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $otherCustomer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();

        $visiblePallet = Pallet::factory()->create(['user_id' => $customer->id, 'current_status_id' => $atCustomer->id]);
        $pickupPallet = Pallet::factory()->create(['user_id' => $customer->id, 'current_status_id' => $pickup->id]);
        $otherPallet = Pallet::factory()->create(['user_id' => $otherCustomer->id, 'current_status_id' => $atCustomer->id]);
        $warehousePallet = Pallet::factory()->create(['user_id' => $customer->id, 'current_status_id' => $warehouse->id]);

        foreach ([$visiblePallet, $pickupPallet] as $pallet) {
            AuditLog::query()->create([
                'pallet_id' => $pallet->id,
                'made_by_user_id' => $admin->id,
                'event_type' => 'status_changed',
                'old_status_id' => $warehouse->id,
                'new_status_id' => $atCustomer->id,
                'new_client_id' => $customer->id,
            ]);
        }
        // This is newer than the delivery transition, but must not hide the
        // delivery photos while the pallet is awaiting customer pickup.
        AuditLog::query()->create([
            'pallet_id' => $pickupPallet->id,
            'made_by_user_id' => $admin->id,
            'event_type' => 'status_changed',
            'old_status_id' => $atCustomer->id,
            'new_status_id' => $pickup->id,
            'new_client_id' => $customer->id,
        ]);

        $visiblePhoto = PalletPhoto::query()->create([
            'pallet_id' => $visiblePallet->id, 'client_id' => $customer->id,
            'uploaded_by_user_id' => $admin->id, 'type' => 'delivery_photo',
            'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);
        $pickupPhoto = PalletPhoto::query()->create([
            'pallet_id' => $pickupPallet->id, 'client_id' => $customer->id,
            'uploaded_by_user_id' => $admin->id, 'type' => 'delivery_photo',
            'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);
        PalletPhoto::query()->create([
            'pallet_id' => $otherPallet->id, 'client_id' => $customer->id,
            'uploaded_by_user_id' => $admin->id, 'type' => 'delivery_photo',
            'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);
        PalletPhoto::query()->create([
            'pallet_id' => $warehousePallet->id, 'client_id' => $customer->id,
            'uploaded_by_user_id' => $admin->id, 'type' => 'delivery_photo',
            'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);
        $legacyDeliveryScan = PalletPhoto::query()->create([
            'pallet_id' => $visiblePallet->id, 'client_id' => $customer->id,
            'uploaded_by_user_id' => $admin->id, 'type' => 'scan',
            'mime_type' => 'image/webp', 'size_bytes' => 5, 'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($customer, 'api')
            ->getJson('/api/gallery')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $visiblePhoto->id])
            ->assertJsonFragment(['id' => $pickupPhoto->id])
            ->assertJsonFragment(['id' => $legacyDeliveryScan->id]);
    }
}
