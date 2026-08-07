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

    public function test_gallery_can_filter_by_customer_and_photo_status(): void
    {
        $admin = $this->makeUser('admin');
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        CustomerDetail::factory()->create(['user_id' => $customerA->id, 'company_name' => 'Customer A']);
        CustomerDetail::factory()->create(['user_id' => $customerB->id, 'company_name' => 'Customer B']);
        $warehouseStatus = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $customerStatus = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $palletA = Pallet::factory()->create([
            'user_id' => $customerA->id,
            'current_status_id' => $warehouseStatus->id,
        ]);
        $palletB = Pallet::factory()->create([
            'user_id' => $customerB->id,
            'current_status_id' => $customerStatus->id,
        ]);
        $photoA = PalletPhoto::query()->create([
            'pallet_id' => $palletA->id,
            'old_status_id' => $customerStatus->id,
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
            'type' => 'scan',
            'mime_type' => 'image/webp',
            'size_bytes' => 5,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson("/api/gallery?client_id={$customerA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photoA->id);

        $this->actingAs($admin, 'api')
            ->getJson("/api/gallery?status_id={$customerStatus->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photoB->id)
            ->assertJsonPath('data.0.status.id', $customerStatus->id);
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
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
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
            ->assertJsonPath('data.height', 1200);

        $photo = PalletPhoto::query()->firstOrFail();

        $this->assertSame('delivery_photo', $photo->type->value);
        $this->assertNull($photo->disk);
        $this->assertNull($photo->path);
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
    }
}
