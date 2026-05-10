<?php

namespace Database\Seeders;

use App\Modules\Modules\Models\Module;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\Roles\Models\Role;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Statuses\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed core lookup data.
     */
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

        $modules = collect($this->moduleDefinitions())
            ->mapWithKeys(function (array $definition): array {
                $module = $this->upsertModule($definition);

                return [$definition['slug'] => $module];
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

        Module::query()
            ->whereIn('slug', ['modules', 'role_permissions', 'customer_details', 'service_reports'])
            ->whereNotIn('id', $modules->pluck('id')->all())
            ->update(['is_active' => false]);

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
            Status::query()->updateOrCreate(
                ['slug' => $status['slug']],
                $status,
            );
        });
    }

    /**
     * @return array<int, array{slug: string, name: string, description: string, legacy_slugs: array<int, string>}>
     */
    private function moduleDefinitions(): array
    {
        return [
            [
                'slug' => ModuleKey::Pallets->value,
                'name' => 'Pallets',
                'description' => 'Pallet tracking and lifecycle management.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::Customers->value,
                'name' => 'Customers',
                'description' => 'Customer management based on customer details records.',
                'legacy_slugs' => ['customer_details'],
            ],
            [
                'slug' => ModuleKey::Roles->value,
                'name' => 'Roles',
                'description' => 'Role and access management.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::Invoices->value,
                'name' => 'Invoices',
                'description' => 'Invoice management.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::InvoiceItems->value,
                'name' => 'Invoice Items',
                'description' => 'Invoice line item management.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::KnowledgeBase->value,
                'name' => 'Knowledge Base',
                'description' => 'Role-aware knowledge base content.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::Statuses->value,
                'name' => 'Statuses',
                'description' => 'Status changes, including QR-driven scanning flows.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::AuditLogs->value,
                'name' => 'Audit Logs',
                'description' => 'Audit trail and activity history.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::QrVersions->value,
                'name' => 'QR Versions',
                'description' => 'QR version management.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::Services->value,
                'name' => 'Services',
                'description' => 'Service and repair workflows.',
                'legacy_slugs' => ['service_reports'],
            ],
            [
                'slug' => ModuleKey::Users->value,
                'name' => 'Users',
                'description' => 'User administration.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::GhostPalletReports->value,
                'name' => 'Ghost Pallet Reports',
                'description' => 'Ghost pallet reporting and pairing.',
                'legacy_slugs' => [],
            ],
        ];
    }

    /**
     * @param  array{slug: string, name: string, description: string, legacy_slugs: array<int, string>}  $definition
     */
    private function upsertModule(array $definition): Module
    {
        return DB::transaction(function () use ($definition): Module {
            $target = Module::query()->where('slug', $definition['slug'])->first();
            $legacyModules = Module::query()
                ->whereIn('slug', $definition['legacy_slugs'])
                ->orderBy('id')
                ->get();

            if (! $target instanceof Module && $legacyModules->isNotEmpty()) {
                /** @var Module $target */
                $target = $legacyModules->shift();
                $target->slug = $definition['slug'];
            }

            if (! $target instanceof Module) {
                /** @var Module $target */
                $target = new Module();
                $target->slug = $definition['slug'];
            }

            $target->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_active' => true,
            ]);
            $target->slug = $definition['slug'];
            $target->save();

            if ($legacyModules->isNotEmpty()) {
                $this->migrateLegacyPermissions($legacyModules, $target);

                Module::query()
                    ->whereIn('id', $legacyModules->pluck('id')->all())
                    ->update(['is_active' => false]);
            }

            return $target;
        });
    }

    /**
     * @param  Collection<int, Module>  $legacyModules
     */
    private function migrateLegacyPermissions(Collection $legacyModules, Module $target): void
    {
        foreach ($legacyModules as $legacyModule) {
            foreach ($legacyModule->rolePermissions as $rolePermission) {
                $existing = RolePermission::query()->firstOrNew([
                    'role_id' => $rolePermission->role_id,
                    'module_id' => $target->id,
                ]);

                $existing->fill([
                    'can_list' => (bool) ($existing->can_list || $rolePermission->can_list),
                    'can_view' => (bool) ($existing->can_view || $rolePermission->can_view),
                    'can_create' => (bool) ($existing->can_create || $rolePermission->can_create),
                    'can_update' => (bool) ($existing->can_update || $rolePermission->can_update),
                    'can_delete' => (bool) ($existing->can_delete || $rolePermission->can_delete),
                ]);
                $existing->save();
            }

            RolePermission::query()->where('module_id', $legacyModule->id)->delete();
        }
    }
}
