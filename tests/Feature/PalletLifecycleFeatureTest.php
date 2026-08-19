<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\DeliveryLocations\Models\DeliveryLocation;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalletLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_pallet_listing_can_be_scoped_to_qr_code_presence(): void
    {
        $admin = $this->makeUser('admin');
        $status = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $qrPallet = Pallet::factory()->create([
            'current_status_id' => $status->id,
            'qr_code' => 'QR-TRACKED-001',
            'is_ghost' => false,
        ]);
        $noQrPallet = Pallet::factory()->create([
            'current_status_id' => $status->id,
            'qr_code' => null,
            'is_ghost' => false,
        ]);
        $ghostPalletWithDisplayReference = Pallet::factory()->create([
            'current_status_id' => $status->id,
            // Legacy ghost records may have had their generated reference
            // copied into qr_code. They are still no-QR pallets.
            'qr_code' => 'PWNQRC-0001',
            'pallet_name' => 'PWNQRC-0001',
            'is_ghost' => true,
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/pallets?has_qr_code=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $qrPallet->id)
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'api')
            ->getJson('/api/pallets?has_qr_code=0')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $noQrPallet->id])
            ->assertJsonFragment(['id' => $ghostPalletWithDisplayReference->id]);
    }

    public function test_customers_see_no_qr_pallets_only_when_at_customer_or_pending_pickup(): void
    {
        $customer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pendingPickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $unknown = Status::query()->where('slug', 'onbekend')->firstOrFail();
        $atCustomerGhost = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'is_ghost' => true,
            'qr_code' => null,
        ]);
        $pendingPickupGhost = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $pendingPickup->id,
            'is_ghost' => true,
            'qr_code' => null,
        ]);
        $unknownGhost = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $unknown->id,
            'is_ghost' => true,
            'qr_code' => null,
        ]);

        $this->actingAs($customer, 'api')
            ->getJson('/api/pallets?has_qr_code=0')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $atCustomerGhost->id])
            ->assertJsonFragment(['id' => $pendingPickupGhost->id]);
    }

    public function test_pallet_listing_can_be_scoped_to_records_updated_since_a_cursor(): void
    {
        $admin = $this->makeUser('admin');
        $status = Status::query()->where('slug', 'bowido-nl')->firstOrFail();

        Carbon::setTestNow('2026-08-01 09:00:00');
        $olderPallet = Pallet::factory()->create(['current_status_id' => $status->id]);
        Carbon::setTestNow('2026-08-02 09:00:00');
        $updatedPallet = Pallet::factory()->create(['current_status_id' => $status->id]);

        try {
            $this->actingAs($admin, 'api')
                ->getJson('/api/pallets?updated_since=2026-08-02T00%3A00%3A00Z')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $updatedPallet->id);
        } finally {
            Carbon::setTestNow();
        }

    }

    public function test_customer_pickup_freezes_the_return_and_deadline_timer(): void
    {
        $customer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $startedAt = Carbon::parse('2026-08-01 09:00:00');
        $frozenAt = Carbon::parse('2026-08-06 14:30:00');
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => $startedAt,
        ]);

        Carbon::setTestNow($frozenAt);

        try {
            $this->actingAs($customer, 'api')
                ->putJson('/api/pallets/'.$pallet->id.'/client-status', [
                    'current_status_id' => $pickup->id,
                ])
                ->assertOk()
                ->assertJsonPath('data.current_status_id', $pickup->id);

            $pallet->refresh();

            $this->assertTrue($pallet->customer_timer_started_at->equalTo($startedAt));
            $this->assertTrue($pallet->customer_timer_frozen_at->equalTo($frozenAt));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_return_reversal_keeps_the_original_customer_timer_for_the_same_customer(): void
    {
        $customer = $this->makeUser('customer');
        $admin = $this->makeUser('admin');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $startedAt = Carbon::parse('2026-08-01 09:00:00');
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => $startedAt,
            'customer_timer_started_at' => $startedAt,
        ]);

        Carbon::setTestNow('2026-08-06 14:30:00');

        try {
            $this->actingAs($customer, 'api')
                ->putJson('/api/pallets/'.$pallet->id.'/client-status', [
                    'current_status_id' => $pickup->id,
                ])
                ->assertOk();

            Carbon::setTestNow('2026-08-09 11:15:00');

            $this->actingAs($customer, 'api')
                ->putJson('/api/pallets/'.$pallet->id.'/client-status', [
                    'current_status_id' => $atCustomer->id,
                ])
                ->assertOk();

            $pallet->refresh();
            $this->assertTrue($pallet->last_status_changed_at->equalTo(now()));
            $this->assertTrue($pallet->customer_timer_started_at->equalTo($startedAt));
            $this->assertNull($pallet->customer_timer_frozen_at);

            Carbon::setTestNow('2026-08-10 08:00:00');
            $this->actingAs($admin, 'api')
                ->putJson('/api/pallets/'.$pallet->id, [
                    'current_status_id' => $pickup->id,
                ])
                ->assertOk();

            Carbon::setTestNow('2026-08-11 08:00:00');
            $this->actingAs($admin, 'api')
                ->putJson('/api/pallets/'.$pallet->id, [
                    'current_status_id' => $atCustomer->id,
                ])
                ->assertOk();

            $pallet->refresh();
            $this->assertTrue($pallet->customer_timer_started_at->equalTo($startedAt));
            $this->assertNull($pallet->customer_timer_frozen_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_current_location_update_persists_the_location_chosen_in_the_client_list(): void
    {
        $admin = $this->makeUser('admin');
        $status = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $this->makeUser('customer')->id,
            'current_status_id' => $status->id,
            'current_location' => 'Old location',
        ]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/current-location', [
                'current_location' => 'Warehouse 2, Example Street 10',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_location', 'Warehouse 2, Example Street 10');

        $this->assertDatabaseHas('pallets', [
            'id' => $pallet->id,
            'current_location' => 'Warehouse 2, Example Street 10',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => AuditEventType::LocationChanged->value,
            'old_location' => 'Old location',
            'new_location' => 'Warehouse 2, Example Street 10',
        ]);
    }

    public function test_customer_status_with_location_is_atomic_and_location_only_change_does_not_restart_counter(): void
    {
        $customer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $returnStatus = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $originalStartedAt = Carbon::parse('2026-08-01 09:00:00');
        $pallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'current_location' => 'Warehouse 1',
            'last_status_changed_at' => $originalStartedAt,
        ]);

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/client-status', [
                'current_status_id' => $atCustomer->id,
                'current_location' => 'Warehouse 2',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_location', 'Warehouse 2');

        $pallet->refresh();
        $this->assertTrue($pallet->last_status_changed_at->equalTo($originalStartedAt));
        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => AuditEventType::LocationChanged->value,
            'old_location' => 'Warehouse 1',
            'new_location' => 'Warehouse 2',
        ]);

        Carbon::setTestNow('2026-08-15 10:30:00');
        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/client-status', [
                'current_status_id' => $returnStatus->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_location', 'Warehouse 2');

        $pallet->refresh();
        $this->assertTrue($pallet->last_status_changed_at->equalTo(now()));
        $this->assertDatabaseHas('audit_logs', [
            'pallet_id' => $pallet->id,
            'event_type' => AuditEventType::StatusChanged->value,
            'old_status_id' => $atCustomer->id,
            'new_status_id' => $returnStatus->id,
            'new_location' => 'Warehouse 2',
        ]);

        Carbon::setTestNow('2026-08-15 11:00:00');
        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/client-status', [
                'current_status_id' => $atCustomer->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_status_id', $atCustomer->id)
            ->assertJsonPath('data.current_location', 'Warehouse 2');

        $pallet->refresh();
        $this->assertTrue($pallet->last_status_changed_at->equalTo(now()));
        Carbon::setTestNow();
    }

    public function test_changing_only_the_customer_restarts_the_return_and_deadline_timer(): void
    {
        $admin = $this->makeUser('admin');
        $originalCustomer = $this->makeUser('customer');
        $newCustomer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $originalCustomer->id,
            'current_status_id' => $atCustomer->id,
            'last_status_changed_at' => Carbon::parse('2026-08-01 09:00:00'),
            'customer_timer_started_at' => Carbon::parse('2026-08-01 09:00:00'),
            'customer_timer_frozen_at' => null,
        ]);

        Carbon::setTestNow('2026-08-16 11:45:00');

        try {
            $this->actingAs($admin, 'api')
                ->putJson('/api/pallets/'.$pallet->id, [
                    'user_id' => $newCustomer->id,
                ])
                ->assertOk()
                ->assertJsonPath('data.user_id', $newCustomer->id)
                ->assertJsonPath('data.current_status_id', $atCustomer->id);

            $pallet->refresh();

            $this->assertTrue($pallet->last_status_changed_at->equalTo(now()));
            $this->assertTrue($pallet->customer_timer_started_at->equalTo(now()));
            $this->assertNull($pallet->customer_timer_frozen_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_unknown_pallets_always_have_no_location_and_cannot_be_given_one(): void
    {
        $admin = $this->makeUser('admin');
        $warehouse = Status::query()->where('slug', 'bowido-nl')->firstOrFail();
        $unknown = Status::query()->where('slug', 'onbekend')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'current_status_id' => $warehouse->id,
            'current_location' => 'Known location',
        ]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id, [
                'current_status_id' => $unknown->id,
                'current_location' => 'Location supplied by the client',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_location', null);

        $this->assertDatabaseHas('pallets', [
            'id' => $pallet->id,
            'current_location' => null,
        ]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/current-location', [
                'current_location' => 'Another location',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_location');

        $this->actingAs($admin, 'api')
            ->postJson('/api/pallets', [
                'current_status_id' => $unknown->id,
                'qr_code' => 'unknown-pallet-001',
                'current_location' => 'Location supplied on creation',
            ])
            ->assertCreated()
            ->assertJsonPath('data.current_location', null);
    }

    public function test_customer_can_update_only_their_own_client_tracking_fields(): void
    {
        $customer = $this->makeUser('customer');
        $otherCustomer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $returnStatus = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $ownPallet = Pallet::factory()->create([
            'user_id' => $customer->id,
            'current_status_id' => $atCustomer->id,
            'current_location' => 'Customer address',
        ]);
        $otherPallet = Pallet::factory()->create([
            'user_id' => $otherCustomer->id,
            'current_status_id' => $atCustomer->id,
        ]);

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$ownPallet->id.'/current-location', [
                'current_location' => 'Selected warehouse',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_location', 'Selected warehouse');

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$ownPallet->id.'/client-status', [
                'current_status_id' => $returnStatus->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_status_id', $returnStatus->id);

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$ownPallet->id.'/client-status', [
                'current_status_id' => $atCustomer->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_status_id', $atCustomer->id);

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$otherPallet->id.'/current-location', [
                'current_location' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->actingAs($customer, 'api')
            ->putJson('/api/pallets/'.$ownPallet->id, [
                'current_location' => 'The general update remains restricted',
            ])
            ->assertForbidden();
    }

    public function test_both_transport_directions_always_store_na_putu(): void
    {
        $admin = $this->makeUser('admin');

        foreach (['bih-nl-transport', 'nl-bih-transport'] as $index => $slug) {
            $status = Status::query()->where('slug', $slug)->firstOrFail();

            $this->actingAs($admin, 'api')->postJson('/api/pallets', [
                'current_status_id' => $status->id,
                'qr_code' => 'transport-'.$index,
                'current_location' => 'Frontend supplied location',
            ])->assertCreated()
                ->assertJsonPath('data.current_location', 'Na putu');
        }
    }

    public function test_customer_status_change_keeps_an_unselected_location_empty(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::query()->create([
            'user_id' => $customer->id,
            'company_name' => 'Pickup Customer',
            'warehouse1_street' => 'Pickupstraat',
            'warehouse1_house_number' => '9',
            'warehouse1_postal_code' => '1000 AA',
            'warehouse1_city' => 'Amsterdam',
        ]);
        $transport = Status::query()->where('slug', 'nl-bih-transport')->firstOrFail();
        $pickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => null,
            'current_status_id' => $transport->id,
            'current_location' => 'Na putu',
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'user_id' => $customer->id,
            'current_status_id' => $pickup->id,
            'current_location' => '',
        ])->assertOk()
            ->assertJsonPath('data.current_status_slug', 'ophalen-klant')
            ->assertJsonPath('data.user_id', $customer->id)
            ->assertJsonPath('data.current_location', null);
    }

    public function test_admin_can_change_or_clear_a_pallet_client_assignment(): void
    {
        $admin = $this->makeUser('admin');
        $firstCustomer = $this->makeUser('customer');
        $secondCustomer = $this->makeUser('customer');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $firstCustomer->id,
            'current_status_id' => $atCustomer->id,
        ]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id, [
                'user_id' => $secondCustomer->id,
                'current_status_id' => $atCustomer->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $secondCustomer->id);

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id, [
                'user_id' => null,
                'current_status_id' => $atCustomer->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.user_id', null);

        $this->assertDatabaseHas('pallets', [
            'id' => $pallet->id,
            'user_id' => null,
        ]);
    }

    public function test_customer_pickup_can_use_the_pallet_delivery_address_as_its_primary_location(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('customer');
        CustomerDetail::query()->create([
            'user_id' => $customer->id,
            'company_name' => 'Pickup Customer',
            'warehouse1_street' => 'Pickupstraat',
            'warehouse1_house_number' => '9',
        ]);
        $transport = Status::query()->where('slug', 'bih-nl-transport')->firstOrFail();
        $pickup = Status::query()->where('slug', 'ophalen-klant')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'current_status_id' => $transport->id,
            'current_location' => 'Na putu',
        ]);
        DeliveryLocation::query()->create([
            'pallet_id' => $pallet->id,
            'latitude' => 43.8563,
            'longitude' => 18.4131,
            'street' => 'GPS Ulica',
            'house_number' => '12',
            'postal_code' => '71000',
            'city' => 'Sarajevo',
            'formatted_address' => 'GPS Ulica 12, 71000 Sarajevo',
            'source' => 'device_gps',
            'confirmed_by_user' => true,
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'user_id' => $customer->id,
            'current_status_id' => $pickup->id,
            'current_location' => 'GPS Ulica 12, 71000 Sarajevo',
        ])->assertOk()
            ->assertJsonPath('data.current_status_slug', 'ophalen-klant')
            ->assertJsonPath('data.current_location', 'GPS Ulica 12, 71000 Sarajevo');
    }

    public function test_repair_status_always_uses_the_service_address(): void
    {
        $admin = $this->makeUser('admin');
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();
        $repair = Status::query()->where('slug', 'service')->firstOrFail();
        $pallet = Pallet::factory()->create([
            'user_id' => $this->makeUser('customer')->id,
            'current_status_id' => $atCustomer->id,
            'current_location' => 'Customer address',
        ]);

        $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'current_status_id' => $repair->id,
            'current_location' => 'Ignored frontend location',
        ])->assertOk()
            ->assertJsonPath('data.current_status_slug', 'service')
            ->assertJsonPath('data.current_location', 'Nikole Tesle 71, 74000 Doboj');
    }

    public function test_repair_status_keeps_only_the_most_recent_note(): void
    {
        $admin = $this->makeUser('admin', ['name' => 'Bowido Admin']);
        $pallet = Pallet::factory()->create([
            'notes' => "Previous repair note\nOlder repair note",
            'is_for_repair' => false,
        ]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/pallets/'.$pallet->id.'/repair-status', ['is_for_repair' => true])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Bowido Admin admitted pallet to service.');

        $this->assertSame(
            'Bowido Admin admitted pallet to service.',
            $pallet->fresh()->notes,
        );
    }

    public function test_status_and_qr_changes_create_audit_logs_with_the_previous_and_new_locations(): void
    {
        $admin = $this->makeUser('admin');
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        CustomerDetail::query()->create([
            'user_id' => $customerB->id,
            'company_name' => 'Customer B',
            'warehouse1_street' => 'Industrieweg',
            'warehouse1_house_number' => '10',
            'warehouse1_postal_code' => '1234 AB',
            'warehouse1_city' => 'Utrecht',
        ]);
        $transport = Status::query()->where('slug', 'bih-nl-transport')->firstOrFail();
        $atCustomer = Status::query()->where('slug', 'bij-de-klant')->firstOrFail();

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/pallets', [
            'user_id' => $customerA->id,
            'current_status_id' => $transport->id,
            'qr_code' => ' pal-0001 ',
            'current_location' => 'Ignored transport location',
            'notes' => 'First scan',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.qr_code', 'PAL-0001')
            ->assertJsonPath('data.current_status_id', $transport->id);

        $pallet = Pallet::query()->firstOrFail();

        $this->assertDatabaseCount('audit_logs', 0);

        $updateResponse = $this->actingAs($admin, 'api')->putJson('/api/pallets/'.$pallet->id, [
            'user_id' => $customerB->id,
            'current_status_id' => $atCustomer->id,
            'asset_type' => 'pallet',
            'qr_code' => 'pal-0002',
            'reference_code' => 'RF-22',
            'current_location' => '',
            'notes' => 'Delivered to customer',
            'is_active' => true,
            'is_ghost' => false,
            'metadata' => ['source' => 'test'],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.user_id', $customerB->id)
            ->assertJsonPath('data.current_status_id', $atCustomer->id)
            ->assertJsonPath('data.qr_code', 'PAL-0002')
            ->assertJsonPath('data.current_location', null);

        $auditLogs = AuditLog::query()
            ->where('pallet_id', $pallet->id)
            ->get()
            ->keyBy('event_type');

        $this->assertEqualsCanonicalizing([
            AuditEventType::StatusChanged->value,
            AuditEventType::QrCodeChanged->value,
        ], $auditLogs->keys()->all());

        $statusLog = $auditLogs->get(AuditEventType::StatusChanged->value);
        $this->assertNotNull($statusLog);
        $this->assertSame($transport->id, $statusLog->old_status_id);
        $this->assertSame($atCustomer->id, $statusLog->new_status_id);
        $this->assertSame('Na putu', $statusLog->old_location);
        $this->assertNull($statusLog->new_location);

        $qrLog = $auditLogs->get(AuditEventType::QrCodeChanged->value);
        $this->assertNotNull($qrLog);
        $this->assertSame('PAL-0001', $qrLog->old_qr_code);
        $this->assertSame('PAL-0002', $qrLog->new_qr_code);

        $events = $auditLogs->keys()
            ->all();

        $this->assertEqualsCanonicalizing([
            AuditEventType::StatusChanged->value,
            AuditEventType::QrCodeChanged->value,
        ], $events);

        $this->assertNotNull($pallet->fresh()->last_status_changed_at);
    }
}
