<?php

namespace Tests\Feature;

use App\Models\Pallet;
use App\Models\ServiceReport;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceWorkflowFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_upload_creates_report_and_moves_pallet_to_service(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'at_customer')->firstOrFail();
        $service = Status::query()->where('slug', 'service')->firstOrFail();

        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
        ]);

        $this->actingAs($admin, 'api')
            ->post('/api/service/pallets/'.$pallet->id.'/report', [
                'problem_description' => 'Broken corner and damaged board',
                'images' => [
                    UploadedFile::fake()->image('damage.jpg'),
                ],
                'location' => 'Doboj service',
            ], [
                'Accept' => 'application/json',
            ])->assertCreated()
            ->assertJsonPath('data.problem_description', 'Broken corner and damaged board');

        $report = ServiceReport::query()->firstOrFail();

        $this->assertSame($service->id, $pallet->fresh()->current_status_id);
        $this->assertNotNull($report->image_path);
        Storage::disk('public')->assertExists($report->image_path);
    }
}
