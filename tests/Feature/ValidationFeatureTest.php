<?php

namespace Tests\Feature;

use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_pallet_creation_requires_qr_code(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $status = Status::query()->where('slug', 'received')->firstOrFail();

        $this->actingAs($admin, 'api')->postJson('/api/pallets', [
            'user_id' => $customer->id,
            'current_status_id' => $status->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['qr_code']);
    }
}
