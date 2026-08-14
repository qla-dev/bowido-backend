<?php

namespace Tests\Feature;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusConfigurationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_billing_configuration_updates_every_client(): void
    {
        $admin = $this->makeUser('admin');
        $firstClient = CustomerDetail::factory()->create([
            'grace_period_days' => 2,
            'default_price_per_day' => 1.50,
        ]);
        $secondClient = CustomerDetail::factory()->create([
            'grace_period_days' => 30,
            'default_price_per_day' => 8.00,
        ]);
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();

        $this->actingAs($admin, 'api')
            ->putJson("/api/statuses/{$status->id}", [
                'is_billable' => false,
                'grace_period_days' => 21,
                'price_per_day' => 4.75,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_billable', false)
            ->assertJsonPath('data.grace_period_days', 21)
            ->assertJsonPath('data.price_per_day', 4.75);

        foreach ([$firstClient, $secondClient] as $client) {
            $this->assertDatabaseHas('customer_details', [
                'id' => $client->id,
                'grace_period_days' => 21,
                'default_price_per_day' => 4.75,
            ]);
        }
    }
}
