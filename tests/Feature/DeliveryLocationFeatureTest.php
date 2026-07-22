<?php

namespace Tests\Feature;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\DeliveryLocations\Models\DeliveryLocation;
use App\Modules\Locations\Providers\GeoapifyLocationProvider;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DeliveryLocationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('location.provider', 'geoapify');
        config()->set('location.geoapify.api_key', 'test-geoapify-key');
        config()->set('location.cache.ttl_seconds', 60);
        Cache::flush();
    }

    public function test_authorized_user_can_reverse_geocode_coordinates(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);

        $response = $this->actingAs($this->makeUser('admin'), 'api')
            ->postJson('/api/location/reverse-geocode', [
                'latitude' => 43.8563,
                'longitude' => 18.4131,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.formatted_address', 'Titova 1, 71000 Sarajevo, Bosnia and Herzegovina')
            ->assertJsonPath('data.street', 'Titova')
            ->assertJsonPath('data.house_number', '1')
            ->assertJsonPath('data.city', 'Sarajevo')
            ->assertJsonPath('data.country_code', 'ba')
            ->assertJsonPath('data.provider', 'geoapify');
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->actingAs($this->makeUser('admin'), 'api')
            ->postJson('/api/location/reverse-geocode', [
                'latitude' => 91,
                'longitude' => -181,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_unauthenticated_user_cannot_reverse_geocode(): void
    {
        $this->postJson('/api/location/reverse-geocode', [
            'latitude' => 43.8563,
            'longitude' => 18.4131,
        ])->assertUnauthorized();
    }

    public function test_provider_failure_returns_a_controlled_response(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response(['message' => 'raw provider failure'], 500),
        ]);

        $this->actingAs($this->makeUser('admin'), 'api')
            ->postJson('/api/location/reverse-geocode', [
                'latitude' => 43.8563,
                'longitude' => 18.4131,
            ])
            ->assertStatus(502)
            ->assertJsonMissing(['message' => 'raw provider failure'])
            ->assertJsonPath('message', 'The address service could not resolve this position.');
    }

    public function test_missing_provider_key_returns_a_controlled_response(): void
    {
        config()->set('location.geoapify.api_key', '');

        $this->actingAs($this->makeUser('admin'), 'api')
            ->postJson('/api/location/reverse-geocode', [
                'latitude' => 43.8563,
                'longitude' => 18.4131,
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Reverse geocoding is not configured.');
    }

    public function test_reverse_geocoding_results_are_cached_for_rounded_coordinates(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);
        $admin = $this->makeUser('admin');

        foreach ([43.8563001, 43.8563002] as $latitude) {
            $this->actingAs($admin, 'api')->postJson('/api/location/reverse-geocode', [
                'latitude' => $latitude,
                'longitude' => 18.4131001,
            ])->assertOk();
        }

        Http::assertSentCount(1);
    }

    public function test_reverse_geocoding_endpoint_is_rate_limited_per_user(): void
    {
        config()->set('location.rate_limit_per_minute', 1);
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);
        $admin = $this->makeUser('admin');
        RateLimiter::clear('user:'.$admin->id);

        $this->actingAs($admin, 'api')->postJson('/api/location/reverse-geocode', [
            'latitude' => 43.8563,
            'longitude' => 18.4131,
        ])->assertOk();

        $this->actingAs($admin, 'api')->postJson('/api/location/reverse-geocode', [
            'latitude' => 44.8563,
            'longitude' => 17.4131,
        ])->assertTooManyRequests();
    }

    public function test_location_can_be_saved_and_is_returned_with_pallet_details(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);
        $admin = $this->makeUser('admin');
        $pallet = $this->makePallet();

        $saveResponse = $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
                'latitude' => 43.8563,
                'longitude' => 18.4131,
                'accuracy_meters' => 8.4,
                'captured_at' => now()->subSecond()->toIso8601String(),
            ]);

        $saveResponse
            ->assertOk()
            ->assertJsonPath('data.pallet_id', $pallet->id)
            ->assertJsonPath('data.formatted_address', 'Titova 1, 71000 Sarajevo, Bosnia and Herzegovina')
            ->assertJsonPath('data.source', 'device_gps')
            ->assertJsonPath('data.confirmed_by_user', true);

        $this->assertDatabaseHas('delivery_locations', [
            'pallet_id' => $pallet->id,
            'created_by_user_id' => $admin->id,
            'source' => 'device_gps',
            'confirmed_by_user' => true,
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/pallets/'.$pallet->id)
            ->assertOk()
            ->assertJsonPath('data.delivery_location.latitude', 43.8563)
            ->assertJsonPath('data.delivery_location.city', 'Sarajevo');
    }

    public function test_existing_delivery_location_can_be_updated(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);
        $admin = $this->makeUser('admin');
        $pallet = $this->makePallet();

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
            'latitude' => 43.8563,
            'longitude' => 18.4131,
        ])->assertOk();

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
            'latitude' => 44.1234567,
            'longitude' => 17.7654321,
            'accuracy_meters' => 15,
        ])->assertOk()->assertJsonPath('data.latitude', 44.1234567);

        $this->assertSame(1, DeliveryLocation::query()->where('pallet_id', $pallet->id)->count());
        $this->assertEquals(17.7654321, $pallet->deliveryLocation()->firstOrFail()->longitude);
    }

    public function test_user_confirmed_address_sections_override_provider_values(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);
        $admin = $this->makeUser('admin');
        $pallet = $this->makePallet();

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
            'latitude' => 43.8563,
            'longitude' => 18.4131,
            'street' => 'Nova ulica',
            'house_number' => '12A',
            'postal_code' => '71000',
            'city' => 'Sarajevo',
        ])->assertOk()
            ->assertJsonPath('data.street', 'Nova ulica')
            ->assertJsonPath('data.house_number', '12A')
            ->assertJsonPath('data.postal_code', '71000')
            ->assertJsonPath('data.city', 'Sarajevo')
            ->assertJsonPath('data.formatted_address', 'Nova ulica 12A, 71000 Sarajevo');

        $this->assertDatabaseHas('delivery_locations', [
            'pallet_id' => $pallet->id,
            'street' => 'Nova ulica',
            'house_number' => '12A',
            'postal_code' => '71000',
            'city' => 'Sarajevo',
        ]);
        $this->assertSame('Nova ulica 12A, 71000 Sarajevo', $pallet->fresh()->current_location);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'current_location' => 'Nova ulica 12A, 71000 Sarajevo',
        ])->assertOk()
            ->assertJsonPath('data.current_location', 'Nova ulica 12A, 71000 Sarajevo');
    }

    public function test_saving_delivery_location_updates_current_location_without_changing_warehouses(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        $detail = CustomerDetail::query()->create([
            'user_id' => $customer->id,
            'company_name' => 'Warehouse Test Client',
            'warehouse1_street' => 'Warehouse One Street',
            'warehouse1_house_number' => '10',
            'warehouse2_street' => 'Warehouse Two Street',
            'warehouse2_house_number' => '20',
        ]);
        $pallet = $this->makePallet($customer->id, 'Existing operational location');

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
            'latitude' => 43.8563,
            'longitude' => 18.4131,
        ])->assertOk();

        $this->assertSame(
            'Titova 1, 71000 Sarajevo, Bosnia and Herzegovina',
            $pallet->fresh()->current_location,
        );
        $this->assertSame('Warehouse One Street', $detail->fresh()->warehouse1_street);
        $this->assertSame('Warehouse Two Street', $detail->fresh()->warehouse2_street);
    }

    public function test_user_cannot_update_a_pallet_they_cannot_access(): void
    {
        $customer = $this->makeUser('customer');
        $otherCustomer = $this->makeUser('customer');
        $pallet = $this->makePallet($otherCustomer->id);

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
                'latitude' => 43.8563,
                'longitude' => 18.4131,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('delivery_locations', ['pallet_id' => $pallet->id]);
    }

    public function test_geoapify_response_is_normalized_by_the_provider(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response($this->geoapifyResponse(), 200),
        ]);

        $result = app(GeoapifyLocationProvider::class)->reverseGeocode(43.8563, 18.4131);

        $this->assertSame('geoapify', $result->provider);
        $this->assertSame('Titova', $result->street);
        $this->assertSame('1', $result->houseNumber);
        $this->assertSame('Sarajevo', $result->city);
        $this->assertSame('71000', $result->postalCode);
        $this->assertSame('ba', $result->countryCode);
    }

    public function test_coordinates_can_still_be_saved_when_reverse_geocoding_is_unavailable(): void
    {
        Http::fake([
            'api.geoapify.com/*' => Http::response([], 503),
        ]);
        $admin = $this->makeUser('admin');
        $pallet = $this->makePallet();

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/delivery-location', [
                'latitude' => 43.8563,
                'longitude' => 18.4131,
            ])
            ->assertOk()
            ->assertJsonPath('data.formatted_address', null)
            ->assertJsonPath('data.latitude', 43.8563);
    }

    private function makePallet(?int $customerId = null, string $location = 'Operational location'): Pallet
    {
        $customerId ??= $this->makeUser('customer')->id;
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();

        return Pallet::factory()->create([
            'user_id' => $customerId,
            'current_status_id' => $status->id,
            'current_location' => $location,
        ]);
    }

    /** @return array<string, array<int, array<string, string>>> */
    private function geoapifyResponse(): array
    {
        return [
            'results' => [[
                'formatted' => 'Titova 1, 71000 Sarajevo, Bosnia and Herzegovina',
                'street' => 'Titova',
                'housenumber' => '1',
                'city' => 'Sarajevo',
                'postcode' => '71000',
                'country' => 'Bosnia and Herzegovina',
                'country_code' => 'ba',
            ]],
        ];
    }
}
