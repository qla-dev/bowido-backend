<?php

namespace Database\Seeders;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Roles\Models\Role;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Shared\Enums\AuditEventType;
use App\Modules\Shared\Enums\GhostPalletReportStatus;
use App\Modules\Shared\Enums\InvoiceStatus;
use App\Modules\Shared\Enums\ServiceReportStatus;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
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
            'customer_eindhoven' => [
                'role' => 'customer',
                'name' => 'Eindhoven Parts B.V.',
                'email' => 'eindhoven.parts@example.com',
                'phone_number' => '+31402041120',
                'password' => 'password123',
            ],
            'customer_rotterdam' => [
                'role' => 'customer',
                'name' => 'Rotterdam Fresh Logistics B.V.',
                'email' => 'rotterdam.fresh@example.com',
                'phone_number' => '+31103189914',
                'password' => 'password123',
            ],
            'customer_sarajevo' => [
                'role' => 'customer',
                'name' => 'Sarajevo Trade d.o.o.',
                'email' => 'sarajevo.trade@example.com',
                'phone_number' => '+38733781226',
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
                'country' => 'NL',
                'kvk' => '30294856',
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
                'country' => 'BiH',
                'kvk' => '4201987650008',
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
                'country' => 'BiH',
                'kvk' => '4220011220003',
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
            'customer_eindhoven' => [
                'company_name' => 'Eindhoven Parts B.V.',
                'country' => 'NL',
                'kvk' => '74291836',
                'billing_email' => 'finance@eindhoven-parts.nl',
                'billing_address' => 'Achtseweg Zuid 151, Eindhoven, Netherlands',
                'delivery_address' => 'Veldhovenweg 18, Eindhoven, Netherlands',
                'tax_number' => '74291836',
                'vat_number' => 'NL742918360B01',
                'default_price_per_day' => 1.85,
                'grace_period_days' => 4,
                'notes' => 'Frontend scanner demo customer seeded into the database.',
                'is_active' => true,
            ],
            'customer_rotterdam' => [
                'company_name' => 'Rotterdam Fresh Logistics B.V.',
                'country' => 'NL',
                'kvk' => '80412670',
                'billing_email' => 'billing@rotterdam-fresh.nl',
                'billing_address' => 'Albert Plesmanweg 65, Rotterdam, Netherlands',
                'delivery_address' => 'Waalhaven Zuidzijde 19, Rotterdam, Netherlands',
                'tax_number' => '80412670',
                'vat_number' => 'NL804126700B01',
                'default_price_per_day' => 2.25,
                'grace_period_days' => 3,
                'notes' => 'Frontend location-directory mock customer seeded into the database.',
                'is_active' => true,
            ],
            'customer_sarajevo' => [
                'company_name' => 'Sarajevo Trade d.o.o.',
                'country' => 'BiH',
                'kvk' => '4207812260005',
                'billing_email' => 'finance@sarajevo-trade.ba',
                'billing_address' => 'Kurta Schorka 14, Sarajevo, Bosnia and Herzegovina',
                'delivery_address' => 'Rajlovacka cesta 18, Sarajevo, Bosnia and Herzegovina',
                'tax_number' => '4207812260005',
                'vat_number' => 'BA207812260005',
                'default_price_per_day' => 2.05,
                'grace_period_days' => 2,
                'notes' => 'Frontend location-directory mock customer seeded into the database.',
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
                'status_slug' => 'bowido-nl',
                'qr_code' => 'BOW-PAL-0001',
                'reference_code' => 'REF-NL-0001',
                'type' => 'L Paleta (120x80)',
                'current_location' => 'Bowido NL Warehouse',
                'notes' => 'Received and staged for the next outbound run.',
                'last_status_changed_at' => now()->subDays(1),
                'metadata' => ['source' => 'seed', 'lane' => 'NL'],
            ],
            [
                'user_key' => 'customer_nl',
                'status_slug' => 'bij-de-klant',
                'qr_code' => 'BOW-PAL-0002',
                'reference_code' => 'REF-NL-0002',
                'type' => 'Siva',
                'current_location' => 'Rotterdam Customer Site',
                'notes' => 'At customer and actively billable.',
                'last_status_changed_at' => now()->subDays(6),
                'metadata' => ['source' => 'seed', 'lane' => 'NL'],
            ],
            [
                'user_key' => 'customer_bih',
                'status_slug' => 'bih-nl-transport',
                'qr_code' => 'BOW-PAL-0003',
                'reference_code' => 'REF-TR-0001',
                'type' => 'L Paleta (200x100)',
                'current_location' => 'In Transit to NL',
                'notes' => 'Cross-border shipment currently in transport.',
                'last_status_changed_at' => now()->subDays(2),
                'metadata' => ['source' => 'seed', 'lane' => 'BiH-NL'],
            ],
            [
                'user_key' => 'customer_bih',
                'status_slug' => 'ophalen-klant',
                'qr_code' => 'BOW-PAL-0004',
                'reference_code' => 'REF-RT-0001',
                'type' => 'A Paleta',
                'current_location' => 'Customer Pickup Queue',
                'notes' => 'Billing stopped while pickup is being scheduled.',
                'last_status_changed_at' => now()->subDays(3),
                'metadata' => ['source' => 'seed', 'lane' => 'BiH'],
            ],
            [
                'user_key' => 'customer_export',
                'status_slug' => 'bih-drugo',
                'qr_code' => 'BOW-PAL-0005',
                'reference_code' => 'REF-SR-0001',
                'type' => 'Siva',
                'current_location' => 'Bowido Service Bench',
                'notes' => 'Deck board damaged and awaiting repair assessment.',
                'last_status_changed_at' => now()->subHours(18),
                'metadata' => ['source' => 'seed', 'issue' => 'Damaged deck board'],
            ],
            [
                'user_key' => 'customer_export',
                'status_slug' => 'onbekend',
                'qr_code' => 'BOW-PAL-0006',
                'reference_code' => 'REF-UN-0001',
                'type' => 'A Paleta',
                'current_location' => 'Location Unknown',
                'notes' => 'Requires admin review because the pallet was not tagged during the last scan.',
                'last_status_changed_at' => now()->subDays(7),
                'metadata' => ['source' => 'seed', 'requires_admin_review' => true],
            ],
            [
                'user_key' => 'customer_eindhoven',
                'status_slug' => 'bowido-nl',
                'qr_code' => 'BOWNL-0001',
                'reference_code' => 'FRONTEND-DEMO-0001',
                'type' => 'A120',
                'current_location' => 'Maxwellstraat 2-4, 3316 GP Dordrecht',
                'notes' => 'Seeded from the frontend scanner demo QR list.',
                'last_status_changed_at' => now()->subDays(1),
                'metadata' => ['source' => 'frontend_mock', 'scanner_demo' => true],
            ],
            [
                'user_key' => 'customer_eindhoven',
                'status_slug' => 'bij-de-klant',
                'qr_code' => 'BOWNL-0002',
                'reference_code' => 'FRONTEND-DEMO-0002',
                'type' => 'A80',
                'current_location' => 'Veldhovenweg 18, Eindhoven, Netherlands',
                'notes' => 'Seeded from the frontend scanner demo QR list.',
                'last_status_changed_at' => now()->subDays(8),
                'metadata' => ['source' => 'frontend_mock', 'scanner_demo' => true],
            ],
            [
                'user_key' => 'customer_rotterdam',
                'status_slug' => 'ophalen-klant',
                'qr_code' => 'BOWNL-0003',
                'reference_code' => 'FRONTEND-DEMO-0003',
                'type' => 'T80',
                'current_location' => 'Waalhaven Zuidzijde 19, Rotterdam, Netherlands',
                'notes' => 'Seeded from the frontend scanner demo QR list.',
                'last_status_changed_at' => now()->subDays(3),
                'metadata' => ['source' => 'frontend_mock', 'scanner_demo' => true],
            ],
            [
                'user_key' => 'customer_rotterdam',
                'status_slug' => 'nl-bih-transport',
                'qr_code' => 'BOWNL-0004',
                'reference_code' => 'FRONTEND-DEMO-0004',
                'type' => 'T120',
                'current_location' => 'Truck NL-BIH / A2 Eindhoven',
                'notes' => 'Seeded from the frontend scanner demo QR list.',
                'last_status_changed_at' => now()->subDays(2),
                'metadata' => ['source' => 'frontend_mock', 'scanner_demo' => true, 'lane' => 'NL-BiH'],
            ],
            [
                'user_key' => 'customer_sarajevo',
                'status_slug' => 'bih-drugo',
                'qr_code' => 'BOWNL-0005',
                'reference_code' => 'FRONTEND-DEMO-0005',
                'type' => 'Grijs',
                'current_location' => 'Bowido Service Bench',
                'notes' => 'Seeded from the frontend scanner demo QR list.',
                'last_status_changed_at' => now()->subHours(10),
                'metadata' => ['source' => 'frontend_mock', 'scanner_demo' => true, 'issue' => 'Corner block inspection'],
            ],
        ])->each(function (array $pallet) use ($statuses, $users): void {
            Pallet::query()->updateOrCreate(
                ['qr_code' => $pallet['qr_code']],
                [
                    'user_id' => $users[$pallet['user_key']]->id,
                    'current_status_id' => $statuses[$pallet['status_slug']],
                    'type' => $pallet['type'],
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

        collect([
            [
                'user_key' => 'customer_nl',
                'qr_code' => 'GHOST-0001',
                'reference_code' => 'GHOST-NL-0001',
                'type' => 'L Paleta (120x80)',
                'current_location' => 'Distribution Park 4, Rotterdam',
                'notes' => 'Client reported a return without QR label. Pickup requested at rear dock.',
                'last_status_changed_at' => now()->subDays(4),
                'metadata' => ['source' => 'seed', 'report_type' => 'missing_qr'],
            ],
            [
                'user_key' => 'customer_bih',
                'qr_code' => 'GHOST-0002',
                'reference_code' => 'GHOST-BIH-0001',
                'type' => 'A Paleta',
                'current_location' => 'Rajlovac Logistics Hub',
                'notes' => 'Unlabeled pallet waiting in pickup zone.',
                'last_status_changed_at' => now()->subDays(2),
                'metadata' => ['source' => 'seed', 'report_type' => 'missing_qr'],
            ],
        ])->each(function (array $pallet) use ($statuses, $users): void {
            Pallet::query()->updateOrCreate(
                ['qr_code' => $pallet['qr_code']],
                [
                    'user_id' => $users[$pallet['user_key']]->id,
                    'current_status_id' => $statuses['ophalen-klant'],
                    'type' => $pallet['type'],
                    'asset_type' => 'pallet',
                    'reference_code' => $pallet['reference_code'],
                    'current_location' => $pallet['current_location'],
                    'notes' => $pallet['notes'],
                    'last_status_changed_at' => $pallet['last_status_changed_at'],
                    'is_active' => true,
                    'is_ghost' => true,
                    'metadata' => $pallet['metadata'],
                ],
            );
        });

        $pallets = Pallet::query()
            ->whereIn('qr_code', [
                'BOW-PAL-0001',
                'BOW-PAL-0002',
                'BOW-PAL-0003',
                'BOW-PAL-0004',
                'BOW-PAL-0005',
                'BOW-PAL-0006',
                'BOWNL-0001',
                'BOWNL-0002',
                'BOWNL-0003',
                'BOWNL-0004',
                'BOWNL-0005',
                'GHOST-0001',
                'GHOST-0002',
            ])
            ->get()
            ->keyBy('qr_code');

        collect([
            [
                'pallet_qr' => 'BOW-PAL-0002',
                'actor_key' => 'driver',
                'event_type' => AuditEventType::StatusChanged->value,
                'old_status_slug' => 'bih-nl-transport',
                'new_status_slug' => 'bij-de-klant',
                'old_location' => 'In Transit to NL',
                'new_location' => 'Rotterdam Customer Site',
                'note' => 'Delivered to customer loading area.',
                'created_at' => now()->subDays(6),
            ],
            [
                'pallet_qr' => 'BOW-PAL-0003',
                'actor_key' => 'driver',
                'event_type' => AuditEventType::StatusChanged->value,
                'old_status_slug' => 'bowido-nl',
                'new_status_slug' => 'bih-nl-transport',
                'old_location' => 'Bowido BiH Warehouse',
                'new_location' => 'In Transit to NL',
                'note' => 'Loaded for cross-border shipment.',
                'created_at' => now()->subDays(2),
            ],
            [
                'pallet_qr' => 'BOW-PAL-0005',
                'actor_key' => 'technician',
                'event_type' => AuditEventType::StatusChanged->value,
                'old_status_slug' => 'bij-de-klant',
                'new_status_slug' => 'bih-drugo',
                'old_location' => 'Mostar Export dock',
                'new_location' => 'Bowido Service Bench',
                'note' => 'Sent to service after damaged deck board report.',
                'created_at' => now()->subHours(18),
            ],
            [
                'pallet_qr' => 'GHOST-0001',
                'actor_key' => 'customer_nl',
                'event_type' => AuditEventType::Created->value,
                'old_status_slug' => null,
                'new_status_slug' => 'ophalen-klant',
                'old_location' => null,
                'new_location' => 'Distribution Park 4, Rotterdam',
                'note' => 'Ghost pallet report created by customer.',
                'created_at' => now()->subDays(4),
            ],
            [
                'pallet_qr' => 'BOWNL-0002',
                'actor_key' => 'driver',
                'event_type' => AuditEventType::StatusChanged->value,
                'old_status_slug' => 'nl-bih-transport',
                'new_status_slug' => 'bij-de-klant',
                'old_location' => 'Truck NL-BIH / A2 Eindhoven',
                'new_location' => 'Veldhovenweg 18, Eindhoven, Netherlands',
                'note' => 'Frontend demo pallet delivered to customer.',
                'created_at' => now()->subDays(8),
            ],
            [
                'pallet_qr' => 'BOWNL-0004',
                'actor_key' => 'driver',
                'event_type' => AuditEventType::StatusChanged->value,
                'old_status_slug' => 'bowido-nl',
                'new_status_slug' => 'nl-bih-transport',
                'old_location' => 'Maxwellstraat 2-4, 3316 GP Dordrecht',
                'new_location' => 'Truck NL-BIH / A2 Eindhoven',
                'note' => 'Frontend demo pallet loaded for transport.',
                'created_at' => now()->subDays(2),
            ],
            [
                'pallet_qr' => 'BOWNL-0005',
                'actor_key' => 'technician',
                'event_type' => AuditEventType::StatusChanged->value,
                'old_status_slug' => 'bij-de-klant',
                'new_status_slug' => 'service',
                'old_location' => 'Rajlovacka cesta 18, Sarajevo, Bosnia and Herzegovina',
                'new_location' => 'Bowido Service Bench',
                'note' => 'Frontend demo pallet sent to service.',
                'created_at' => now()->subHours(10),
            ],
        ])->each(function (array $log) use ($pallets, $statuses, $users): void {
            $pallet = $pallets[$log['pallet_qr']] ?? null;

            if (! $pallet instanceof Pallet) {
                return;
            }

            $auditLog = AuditLog::query()->updateOrCreate(
                [
                    'pallet_id' => $pallet->id,
                    'event_type' => $log['event_type'],
                    'note' => $log['note'],
                ],
                [
                    'made_by_user_id' => $users[$log['actor_key']]->id,
                    'old_status_id' => $log['old_status_slug'] ? $statuses[$log['old_status_slug']] : null,
                    'new_status_id' => $log['new_status_slug'] ? $statuses[$log['new_status_slug']] : null,
                    'old_client_id' => $pallet->user_id,
                    'new_client_id' => $pallet->user_id,
                    'old_location' => $log['old_location'],
                    'new_location' => $log['new_location'],
                    'old_qr_code' => null,
                    'new_qr_code' => $pallet->qr_code,
                    'context' => ['source' => 'seed'],
                ],
            );
            $auditLog->forceFill(['created_at' => $log['created_at'], 'updated_at' => $log['created_at']])->save();
        });

        $servicePallet = $pallets['BOW-PAL-0005'] ?? null;

        if ($servicePallet instanceof Pallet) {
            $serviceReport = ServiceReport::query()->updateOrCreate(
                ['pallet_id' => $servicePallet->id, 'status' => ServiceReportStatus::Open->value],
                [
                    'reported_by_user_id' => $users['technician']->id,
                    'resolved_by_user_id' => null,
                    'severity' => 'high',
                    'issue_type' => 'damaged_deck_board',
                    'description' => 'Deck board damaged and awaiting repair assessment.',
                    'resolution_note' => null,
                    'image_path' => 'https://images.unsplash.com/photo-1589939705384-5185138a04b9?auto=format&fit=crop&q=80&w=400',
                    'resolved_at' => null,
                    'metadata' => ['source' => 'seed'],
                ],
            );
            $serviceReport->forceFill(['created_at' => now()->subHours(18), 'updated_at' => now()->subHours(18)])->save();
        }

        $frontendDemoServicePallet = $pallets['BOWNL-0005'] ?? null;

        if ($frontendDemoServicePallet instanceof Pallet) {
            $serviceReport = ServiceReport::query()->updateOrCreate(
                ['pallet_id' => $frontendDemoServicePallet->id, 'status' => ServiceReportStatus::Open->value],
                [
                    'reported_by_user_id' => $users['technician']->id,
                    'resolved_by_user_id' => null,
                    'severity' => 'medium',
                    'issue_type' => 'corner_block_inspection',
                    'description' => 'Frontend demo pallet requires corner block inspection before release.',
                    'resolution_note' => null,
                    'image_path' => null,
                    'resolved_at' => null,
                    'metadata' => ['source' => 'frontend_mock'],
                ],
            );
            $serviceReport->forceFill(['created_at' => now()->subHours(10), 'updated_at' => now()->subHours(10)])->save();
        }

        collect([
            [
                'user_key' => 'customer_nl',
                'pallet_qr' => 'GHOST-0001',
                'quantity' => 1,
                'location' => 'Distribution Park 4, Rotterdam',
                'description' => 'Return reported without QR label.',
                'notes' => 'Pickup requested at rear dock.',
                'created_at' => now()->subDays(4),
            ],
            [
                'user_key' => 'customer_bih',
                'pallet_qr' => 'GHOST-0002',
                'quantity' => 1,
                'location' => 'Rajlovac Logistics Hub',
                'description' => 'Unlabeled pallet waiting in pickup zone.',
                'notes' => 'Driver should verify against customer paperwork.',
                'created_at' => now()->subDays(2),
            ],
        ])->each(function (array $report) use ($pallets, $users): void {
            $ghostReport = GhostPalletReport::query()->updateOrCreate(
                [
                    'user_id' => $users[$report['user_key']]->id,
                    'location' => $report['location'],
                    'description' => $report['description'],
                ],
                [
                    'paired_pallet_id' => null,
                    'status' => GhostPalletReportStatus::Open->value,
                    'quantity' => $report['quantity'],
                    'notes' => $report['notes'],
                    'metadata' => [
                        'source' => 'seed',
                        'ghost_qr_code' => $report['pallet_qr'],
                        'pallet_id' => ($pallets[$report['pallet_qr']] ?? null)?->id,
                    ],
                ],
            );
            $ghostReport->forceFill(['created_at' => $report['created_at'], 'updated_at' => $report['created_at']])->save();
        });

        collect([
            [
                'user_key' => 'customer_nl',
                'invoice_number' => 'INV-2026-001',
                'status' => InvoiceStatus::Issued->value,
                'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
                'issued_at' => now()->subDays(10),
                'due_at' => now()->addDays(4)->toDateString(),
                'paid_at' => null,
                'notes' => 'June pallet storage and return handling.',
                'items' => [
                    ['pallet_qr' => 'BOW-PAL-0002', 'description' => 'Storage Fee (4 billable days)', 'billed_days' => 4, 'price_per_day' => 1.75],
                    ['pallet_qr' => 'GHOST-0001', 'description' => 'No-QR return handling', 'billed_days' => 1, 'price_per_day' => 12.00],
                ],
            ],
            [
                'user_key' => 'customer_bih',
                'invoice_number' => 'INV-2026-002',
                'status' => InvoiceStatus::Paid->value,
                'period_start' => now()->subMonths(2)->startOfMonth()->toDateString(),
                'period_end' => now()->subMonths(2)->endOfMonth()->toDateString(),
                'issued_at' => now()->subDays(38),
                'due_at' => now()->subDays(24)->toDateString(),
                'paid_at' => now()->subDays(20),
                'notes' => 'May transport and storage settlement.',
                'items' => [
                    ['pallet_qr' => 'BOW-PAL-0004', 'description' => 'Pending return handling', 'billed_days' => 2, 'price_per_day' => 2.10],
                ],
            ],
            [
                'user_key' => 'customer_eindhoven',
                'invoice_number' => 'INV-2026-003',
                'status' => InvoiceStatus::Issued->value,
                'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
                'issued_at' => now()->subDays(16),
                'due_at' => now()->subDays(2)->toDateString(),
                'paid_at' => null,
                'notes' => 'Frontend demo scanner pallet billing.',
                'items' => [
                    ['pallet_qr' => 'BOWNL-0002', 'description' => 'Storage Fee (frontend demo pallet)', 'billed_days' => 6, 'price_per_day' => 1.85],
                    ['pallet_qr' => 'BOWNL-0003', 'description' => 'Return handling (frontend demo pallet)', 'billed_days' => 1, 'price_per_day' => 8.00],
                ],
            ],
        ])->each(function (array $invoiceData) use ($pallets, $users): void {
            $subtotal = collect($invoiceData['items'])->sum(
                fn (array $item): float => round($item['billed_days'] * $item['price_per_day'], 2)
            );

            $invoice = Invoice::query()->updateOrCreate(
                ['invoice_number' => $invoiceData['invoice_number']],
                [
                    'user_id' => $users[$invoiceData['user_key']]->id,
                    'status' => $invoiceData['status'],
                    'currency' => 'EUR',
                    'period_start' => $invoiceData['period_start'],
                    'period_end' => $invoiceData['period_end'],
                    'issued_at' => $invoiceData['issued_at'],
                    'due_at' => $invoiceData['due_at'],
                    'paid_at' => $invoiceData['paid_at'],
                    'subtotal_amount' => $subtotal,
                    'total_amount' => $subtotal,
                    'notes' => $invoiceData['notes'],
                ],
            );

            foreach ($invoiceData['items'] as $item) {
                $pallet = $pallets[$item['pallet_qr']] ?? null;

                InvoiceItem::query()->updateOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'pallet_id' => $pallet instanceof Pallet ? $pallet->id : null,
                        'description' => $item['description'],
                    ],
                    [
                        'period_start' => $invoiceData['period_start'],
                        'period_end' => $invoiceData['period_end'],
                        'billed_days' => $item['billed_days'],
                        'price_per_day' => $item['price_per_day'],
                        'amount' => round($item['billed_days'] * $item['price_per_day'], 2),
                        'metadata' => ['source' => 'seed'],
                    ],
                );
            }
        });
    }
}
