<?php

namespace Database\Seeders;

use App\Models\CustomerDetail;
use App\Models\Pallet;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;

class MockDataSeeder extends Seeder
{
    /**
     * Seed demo users, customer details, and pallets.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'name');
        $statuses = Status::query()->pluck('id', 'slug');

        $users = collect([
            'admin' => [
                'role' => 'admin',
                'name' => 'Bowido Admin',
                'email' => 'admin@example.com',
                'phone_number' => '+38761100001',
                'password' => 'password123',
            ],
            'warehouse_operator' => [
                'role' => 'warehouse_operator',
                'name' => 'Warehouse Operator',
                'email' => 'warehouse@example.com',
                'phone_number' => '+38761100002',
                'password' => 'password123',
            ],
            'driver' => [
                'role' => 'driver',
                'name' => 'Transport Driver',
                'email' => 'driver@example.com',
                'phone_number' => '+38761100003',
                'password' => 'password123',
            ],
            'technician' => [
                'role' => 'technician',
                'name' => 'Service Technician',
                'email' => 'technician@example.com',
                'phone_number' => '+38761100004',
                'password' => 'password123',
            ],
            'operator' => [
                'role' => 'operator',
                'name' => 'Operations User',
                'email' => 'operator@example.com',
                'phone_number' => '+38761100005',
                'password' => 'password123',
            ],
            'user' => [
                'role' => 'user',
                'name' => 'Limited User',
                'email' => 'user@example.com',
                'phone_number' => '+38761100006',
                'password' => 'password123',
            ],
            'customer_nl' => [
                'role' => 'customer',
                'name' => 'Eva van Dijk',
                'email' => 'eva.vandijk@example.com',
                'phone_number' => '+31610000001',
                'password' => 'password123',
            ],
            'customer_bih' => [
                'role' => 'customer',
                'name' => 'Amar Kovac',
                'email' => 'amar.kovac@example.com',
                'phone_number' => '+38761100007',
                'password' => 'password123',
            ],
            'customer_export' => [
                'role' => 'customer',
                'name' => 'Lejla Hadzic',
                'email' => 'lejla.hadzic@example.com',
                'phone_number' => '+38761100008',
                'password' => 'password123',
            ],
        ])->mapWithKeys(function (array $user, string $key) use ($roles): array {
            return [
                $key => User::query()->updateOrCreate(
                    ['email' => $user['email']],
                    [
                        'role_id' => $roles[$user['role']],
                        'name' => $user['name'],
                        'phone_number' => $user['phone_number'],
                        'password' => $user['password'],
                        'is_active' => true,
                    ],
                ),
            ];
        });

        collect([
            'customer_nl' => [
                'company_name' => 'Van Dijk Retail B.V.',
                'billing_email' => 'finance@vandijk-retail.nl',
                'billing_address' => 'Waalhaven 12, 3089 Rotterdam, Netherlands',
                'delivery_address' => 'Distribution Park 4, 3197 Rotterdam, Netherlands',
                'tax_number' => '30294856',
                'vat_number' => 'NL123456789B01',
                'default_price_per_day' => 1.75,
                'grace_period_days' => 2,
                'notes' => 'Primary Dutch retail customer.',
                'is_active' => true,
            ],
            'customer_bih' => [
                'company_name' => 'Sarajevo Distribution d.o.o.',
                'billing_email' => 'billing@sarajevo-distribution.ba',
                'billing_address' => 'Zmaja od Bosne 88, 71000 Sarajevo, Bosnia and Herzegovina',
                'delivery_address' => 'Rajlovac Logistics Hub, 71000 Sarajevo, Bosnia and Herzegovina',
                'tax_number' => '4201987650008',
                'vat_number' => 'BA201987650008',
                'default_price_per_day' => 2.10,
                'grace_period_days' => 1,
                'notes' => 'Mixed domestic and cross-border pallet flows.',
                'is_active' => true,
            ],
            'customer_export' => [
                'company_name' => 'Mostar Export d.o.o.',
                'billing_email' => 'accounts@mostar-export.ba',
                'billing_address' => 'Bulevar 45, 88000 Mostar, Bosnia and Herzegovina',
                'delivery_address' => 'Industrial Zone Rodoch, 88000 Mostar, Bosnia and Herzegovina',
                'tax_number' => '4220011220003',
                'vat_number' => 'BA220011220003',
                'default_price_per_day' => 1.95,
                'grace_period_days' => 3,
                'notes' => 'Export customer with recurring return pickups.',
                'is_active' => true,
            ],
        ])->each(function (array $detail, string $userKey) use ($users): void {
            CustomerDetail::query()->updateOrCreate(
                ['user_id' => $users[$userKey]->id],
                [
                    ...$detail,
                    'user_id' => $users[$userKey]->id,
                ],
            );
        });

        collect([
            [
                'user_key' => 'customer_nl',
                'status_slug' => 'bowido_warehouse',
                'qr_code' => 'BOW-PAL-0001',
                'reference_code' => 'REF-NL-0001',
                'current_location' => 'Bowido NL Warehouse',
                'notes' => 'Received and staged for the next outbound run.',
                'last_status_changed_at' => now()->subDays(1),
                'metadata' => ['source' => 'seed', 'lane' => 'NL'],
            ],
            [
                'user_key' => 'customer_nl',
                'status_slug' => 'at_customer',
                'qr_code' => 'BOW-PAL-0002',
                'reference_code' => 'REF-NL-0002',
                'current_location' => 'Rotterdam Customer Site',
                'notes' => 'At customer and actively billable.',
                'last_status_changed_at' => now()->subDays(6),
                'metadata' => ['source' => 'seed', 'lane' => 'NL'],
            ],
            [
                'user_key' => 'customer_bih',
                'status_slug' => 'transport',
                'qr_code' => 'BOW-PAL-0003',
                'reference_code' => 'REF-TR-0001',
                'current_location' => 'In Transit to NL',
                'notes' => 'Cross-border shipment currently in transport.',
                'last_status_changed_at' => now()->subDays(2),
                'metadata' => ['source' => 'seed', 'lane' => 'BiH-NL'],
            ],
            [
                'user_key' => 'customer_bih',
                'status_slug' => 'pending_return',
                'qr_code' => 'BOW-PAL-0004',
                'reference_code' => 'REF-RT-0001',
                'current_location' => 'Customer Pickup Queue',
                'notes' => 'Billing stopped while pickup is being scheduled.',
                'last_status_changed_at' => now()->subDays(3),
                'metadata' => ['source' => 'seed', 'lane' => 'BiH'],
            ],
            [
                'user_key' => 'customer_export',
                'status_slug' => 'service',
                'qr_code' => 'BOW-PAL-0005',
                'reference_code' => 'REF-SR-0001',
                'current_location' => 'Bowido Service Bench',
                'notes' => 'Deck board damaged and awaiting repair assessment.',
                'last_status_changed_at' => now()->subHours(18),
                'metadata' => ['source' => 'seed', 'issue' => 'Damaged deck board'],
            ],
            [
                'user_key' => 'customer_export',
                'status_slug' => 'unknown',
                'qr_code' => 'BOW-PAL-0006',
                'reference_code' => 'REF-UN-0001',
                'current_location' => 'Location Unknown',
                'notes' => 'Requires admin review because the pallet was not tagged during the last scan.',
                'last_status_changed_at' => now()->subDays(7),
                'metadata' => ['source' => 'seed', 'requires_admin_review' => true],
            ],
        ])->each(function (array $pallet) use ($statuses, $users): void {
            Pallet::query()->updateOrCreate(
                ['qr_code' => $pallet['qr_code']],
                [
                    'user_id' => $users[$pallet['user_key']]->id,
                    'current_status_id' => $statuses[$pallet['status_slug']],
                    'asset_type' => 'pallet',
                    'reference_code' => $pallet['reference_code'],
                    'current_location' => $pallet['current_location'],
                    'notes' => $pallet['notes'],
                    'last_status_changed_at' => $pallet['last_status_changed_at'],
                    'is_active' => true,
                    'is_ghost' => false,
                    'metadata' => $pallet['metadata'],
                ],
            );
        });
    }
}
