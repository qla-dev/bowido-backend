<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PalletPhotoFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_photo_is_stored_privately_without_an_audit_log(): void
    {
        Storage::fake('local');

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
        Storage::disk('local')->assertExists($photo->path);
        $this->assertSame($auditLogCount, AuditLog::query()->count());

        $this->actingAs($admin, 'api')
            ->get("/api/customer_details/{$customerDetail->id}/pallet-photos")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photo->id);
    }

    public function test_damage_report_photo_is_stored_with_the_damage_type_without_an_audit_log(): void
    {
        Storage::fake('local');

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
        Storage::disk('local')->assertExists($photo->path);
        $this->assertSame($auditLogCount, AuditLog::query()->count());
    }
}
