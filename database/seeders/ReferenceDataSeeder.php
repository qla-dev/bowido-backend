<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect([
            ['name' => 'admin', 'description' => 'Platform administrator'],
            ['name' => 'warehouse_operator', 'description' => 'Warehouse operations'],
            ['name' => 'customer', 'description' => 'Customer account'],
            ['name' => 'driver', 'description' => 'Transport'],
            ['name' => 'technician', 'description' => 'Service and repair'],
            ['name' => 'user', 'description' => 'Limited access'],
            ['name' => 'operator', 'description' => 'Operational user'],
        ])->mapWithKeys(function (array $role): array {
            return [
                $role['name'] => Role::query()->updateOrCreate(
                    ['name' => $role['name']],
                    [
                        'description' => $role['description'],
                        'is_active' => true,
                    ],
                ),
            ];
        });

        $modules = collect([
            'roles',
            'users',
            'customer_details',
            'statuses',
            'pallets',
            'audit_logs',
            'service_reports',
            'ghost_pallet_reports',
            'invoices',
            'invoice_items',
            'modules',
            'role_permissions',
        ])->mapWithKeys(function (string $slug): array {
            $module = Module::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(str_replace('_', ' ', $slug)),
                    'description' => Str::headline(str_replace('_', ' ', $slug)).' module',
                    'is_active' => true,
                ],
            );

            return [$slug => $module];
        });

        foreach ($modules as $module) {
            RolePermission::query()->updateOrCreate(
                ['role_id' => $roles['admin']->id, 'module_id' => $module->id],
                [
                    'can_list' => true,
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => true,
                ],
            );
        }

        collect([
            [
                'name' => 'Bowido BiH / NL: Warehouse',
                'slug' => 'bowido_warehouse',
                'description' => 'Pallet is stored in a Bowido BiH or Bowido NL warehouse.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Transport (BiH-NL / NL-BiH)',
                'slug' => 'transport',
                'description' => 'Pallet is in transport between Bosnia and Herzegovina and the Netherlands. An internal counter can be used to raise a warning after 3 days.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'At Customer',
                'slug' => 'at_customer',
                'description' => 'Pallet is at the customer location and active billing starts once a customer is assigned.',
                'is_billable' => true,
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Pending Return',
                'slug' => 'pending_return',
                'description' => 'Billing is stopped, but the pallet remains with the customer until pickup is completed.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'name' => 'Service',
                'slug' => 'service',
                'description' => 'Pallet is under repair. When entering this status, the problem description and a damage photo should be captured.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'name' => 'Unknown',
                'slug' => 'unknown',
                'description' => 'Status for pallets that are untagged or lost. This status should only be assigned by an administrator.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 60,
            ],
        ])->each(function (array $status): void {
            Status::query()->updateOrCreate(['slug' => $status['slug']], $status);
        });
    }
}