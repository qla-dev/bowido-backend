<?php

namespace Tests\Feature;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalletDashboardStatsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_are_calculated_from_database(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'grace_period_days' => 2,
        ]);
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $transport = Status::query()->where('slug', 'bih-nl-transport')->firstOrFail();
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();

        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $warehouse->id,
            'last_status_changed_at' => now()->subDay(),
        ]);
        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $transport->id,
            'last_status_changed_at' => now()->subDay(),
        ]);
        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $transport->id,
            'last_status_changed_at' => now()->subDays(5),
        ]);
        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(3),
        ]);
        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/pallets/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('data.total_pallets', 5)
            ->assertJsonPath('data.in_transport', 2)
            ->assertJsonPath('data.overdue_units', 1);
    }

    public function test_customer_dashboard_stats_only_include_pallets_at_or_returning_from_customer(): void
    {
        $customer = $this->makeUser('customer');
        $otherCustomer = $this->makeUser('customer');
        CustomerDetail::factory()->create([
            'user_id' => $customer->id,
            'grace_period_days' => 1,
        ]);
        CustomerDetail::factory()->create([
            'user_id' => $otherCustomer->id,
            'grace_period_days' => 1,
        ]);
        $transport = Status::query()->where('slug', 'bih-nl-transport')->firstOrFail();
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();

        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $transport->id,
            'last_status_changed_at' => now()->subDay(),
        ]);
        Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(2),
        ]);
        Pallet::factory()->create([
            'user_id' => $otherCustomer->id,
            'current_status_id' => $transport->id,
            'last_status_changed_at' => now()->subDay(),
        ]);
        Pallet::factory()->create([
            'user_id' => $otherCustomer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => now()->subDays(2),
        ]);

        $this->actingAs($customer, 'api')
            ->getJson('/api/pallets/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('data.total_pallets', 1)
            ->assertJsonPath('data.in_transport', 0)
            ->assertJsonPath('data.overdue_units', 1);
    }
}
