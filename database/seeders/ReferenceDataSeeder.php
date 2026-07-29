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

        $this->syncRolePermissions($roles, $modules);

        Module::query()
            ->whereIn('slug', ['modules', 'role_permissions', 'customer_details', 'service_reports'])
            ->whereNotIn('id', $modules->pluck('id')->all())
            ->update(['is_active' => false]);

        collect([
            [
                'name' => 'Bowido NL',
                'slug' => 'bowido-nl',
                'description' => 'Pallet is stored at the Bowido Netherlands warehouse.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Bij de klant',
                'slug' => 'bij-de-klant',
                'description' => 'Pallet is at the customer location and active billing starts once a customer is assigned.',
                'is_billable' => true,
                'grace_period_days' => 14,
                'price_per_day' => 2.50,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Ophalen klant',
                'slug' => 'ophalen-klant',
                'description' => 'Pallet remains with the customer until pickup is completed.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'BiH-NL transport',
                'slug' => 'bih-nl-transport',
                'description' => 'Pallet is in transport from Bosnia and Herzegovina to the Netherlands.',
                'is_billable' => false,
                'grace_period_days' => 3,
                'price_per_day' => 0,
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'name' => 'Bowido BiH',
                'slug' => 'bowido-bih',
                'description' => 'Pallet is stored at the Bowido Bosnia and Herzegovina warehouse.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'name' => 'NL-BiH transport',
                'slug' => 'nl-bih-transport',
                'description' => 'Pallet is in transport from the Netherlands to Bosnia and Herzegovina.',
                'is_billable' => false,
                'grace_period_days' => 3,
                'price_per_day' => 0,
                'is_active' => true,
                'sort_order' => 60,
            ],
            [
                'name' => 'Onbekend',
                'slug' => 'onbekend',
                'description' => 'Status for pallets that are untagged or lost. This status should only be assigned by an administrator.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 70,
            ],
            [
                'name' => 'BiH-Drugo',
                'slug' => 'bih-drugo',
                'description' => 'Pallet is in another Bosnia and Herzegovina operational state.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 80,
            ],
            [
                'name' => 'Voor reparatie',
                'slug' => 'service',
                'description' => 'Pallet is in Bowido service or repair.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 90,
            ],
        ])->each(function (array $status): void {
            Status::query()->updateOrCreate(
                ['slug' => $status['slug']],
                $status,
            );
        });
    }

    /**
     * @param  Collection<string, Role>  $roles
     * @param  Collection<string, Module>  $modules
     */
    private function syncRolePermissions(Collection $roles, Collection $modules): void
    {
        foreach ($roles as $roleName => $role) {
            $permissions = $this->permissionsForRole($roleName, $modules);

            foreach ($permissions as $permission) {
                $attributes = [
                    'can_list' => $permission['can_list'],
                    'can_view' => $permission['can_view'],
                    'can_create' => $permission['can_create'],
                    'can_update' => $permission['can_update'],
                    'can_delete' => $permission['can_delete'],
                ];

                if ($roleName === 'admin') {
                    RolePermission::query()->updateOrCreate(
                        ['role_id' => $role->id, 'module_id' => $permission['module_id']],
                        $attributes,
                    );

                    continue;
                }

                RolePermission::query()->firstOrCreate(
                    ['role_id' => $role->id, 'module_id' => $permission['module_id']],
                    $attributes,
                );
            }
        }
    }

    /**
     * @param  Collection<string, Module>  $modules
     * @return array<int, array{module_id: int, can_list: bool, can_view: bool, can_create: bool, can_update: bool, can_delete: bool}>
     */
    private function permissionsForRole(string $roleName, Collection $modules): array
    {
        if ($roleName === 'admin') {
            return $modules
                ->map(fn (Module $module): array => $this->permissionRow($module, true, true, true, true, true))
                ->values()
                ->all();
        }

        $matrix = [
            'warehouse_operator' => [
                ModuleKey::Pallets->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::Customers->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::Statuses->value => ['can_list', 'can_view', 'can_update'],
                ModuleKey::AuditLogs->value => ['can_list', 'can_view'],
                ModuleKey::Services->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::GhostPalletReports->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::Invoices->value => ['can_list', 'can_view'],
                ModuleKey::InvoiceItems->value => ['can_list', 'can_view'],
            ],
            'operator' => [
                ModuleKey::Pallets->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::Customers->value => ['can_list', 'can_view'],
                ModuleKey::Statuses->value => ['can_list', 'can_view'],
                ModuleKey::AuditLogs->value => ['can_list', 'can_view'],
                ModuleKey::Services->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::GhostPalletReports->value => ['can_list', 'can_view', 'can_create', 'can_update'],
            ],
            'driver' => [
                ModuleKey::Pallets->value => ['can_list', 'can_view', 'can_update'],
                ModuleKey::Customers->value => ['can_list', 'can_view'],
                ModuleKey::Statuses->value => ['can_list', 'can_view'],
                ModuleKey::AuditLogs->value => ['can_list', 'can_view'],
                ModuleKey::GhostPalletReports->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::Services->value => ['can_list', 'can_view'],
            ],
            'technician' => [
                ModuleKey::Pallets->value => ['can_list', 'can_view', 'can_update'],
                ModuleKey::Customers->value => ['can_list', 'can_view'],
                ModuleKey::Statuses->value => ['can_list', 'can_view'],
                ModuleKey::AuditLogs->value => ['can_list', 'can_view'],
                ModuleKey::Services->value => ['can_list', 'can_view', 'can_create', 'can_update'],
                ModuleKey::GhostPalletReports->value => ['can_list', 'can_view'],
            ],
            'customer' => [
                ModuleKey::Pallets->value => ['can_list', 'can_view'],
                ModuleKey::Customers->value => ['can_list', 'can_view', 'can_update'],
                ModuleKey::Statuses->value => ['can_list', 'can_view'],
                ModuleKey::AuditLogs->value => ['can_list', 'can_view'],
                ModuleKey::Invoices->value => ['can_list', 'can_view'],
                ModuleKey::InvoiceItems->value => ['can_list', 'can_view'],
                ModuleKey::Services->value => ['can_list', 'can_view', 'can_create'],
                ModuleKey::GhostPalletReports->value => ['can_list', 'can_view', 'can_create'],
            ],
            'user' => [
                ModuleKey::Pallets->value => ['can_list', 'can_view'],
                ModuleKey::Statuses->value => ['can_list', 'can_view'],
                ModuleKey::AuditLogs->value => ['can_list', 'can_view'],
            ],
        ];

        return collect($matrix[$roleName] ?? [])
            ->map(function (array $abilities, string $moduleSlug) use ($modules): ?array {
                $module = $modules->get($moduleSlug);

                if (! $module instanceof Module) {
                    return null;
                }

                return $this->permissionRow(
                    module: $module,
                    canList: in_array('can_list', $abilities, true),
                    canView: in_array('can_view', $abilities, true),
                    canCreate: in_array('can_create', $abilities, true),
                    canUpdate: in_array('can_update', $abilities, true),
                    canDelete: in_array('can_delete', $abilities, true),
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{module_id: int, can_list: bool, can_view: bool, can_create: bool, can_update: bool, can_delete: bool}
     */
    private function permissionRow(
        Module $module,
        bool $canList,
        bool $canView,
        bool $canCreate,
        bool $canUpdate,
        bool $canDelete,
    ): array {
        return [
            'module_id' => $module->id,
            'can_list' => $canList,
            'can_view' => $canView,
            'can_create' => $canCreate,
            'can_update' => $canUpdate,
            'can_delete' => $canDelete,
        ];
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
                'name' => 'Pallets without QR codes',
                'description' => 'Reporting and pairing pallets without QR codes.',
                'legacy_slugs' => [],
            ],
            [
                'slug' => ModuleKey::ImageGallery->value,
                'name' => 'Image Gallery',
                'description' => 'Secure pallet image gallery with warehouse visibility scopes.',
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
                $target = new Module;
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
