<?php

namespace Database\Seeders;

use App\Modules\Modules\Models\Module;
use App\Modules\Roles\Models\Role;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Statuses\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed core lookup data.
     */
    public function run(): void
    {
        $roles = collect([
            'admin' => Role::query()->firstOrCreate(
                ['name' => 'admin'],
                ['description' => 'Platform administrator', 'is_active' => true],
            ),
            'operator' => Role::query()->firstOrCreate(
                ['name' => 'operator'],
                ['description' => 'Operational user', 'is_active' => true],
            ),
            'customer' => Role::query()->firstOrCreate(
                ['name' => 'customer'],
                ['description' => 'Customer account', 'is_active' => true],
            ),
        ]);

        $modules = collect(ModuleKey::cases())
            ->mapWithKeys(function (ModuleKey $moduleKey): array {
                $module = Module::query()->firstOrCreate(
                    ['slug' => $moduleKey->value],
                    [
                        'name' => Str::headline(str_replace('_', ' ', $moduleKey->value)),
                        'description' => Str::headline(str_replace('_', ' ', $moduleKey->value)).' module',
                        'is_active' => true,
                    ],
                );

                return [$moduleKey->value => $module];
            });

        foreach ($modules as $module) {
            RolePermission::query()->firstOrCreate(
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

        Status::query()->firstOrCreate(
            ['slug' => 'received'],
            [
                'name' => 'Received',
                'description' => 'Asset received and awaiting storage.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        Status::query()->firstOrCreate(
            ['slug' => 'stored'],
            [
                'name' => 'Stored',
                'description' => 'Asset is in billable storage.',
                'is_billable' => true,
                'is_active' => true,
                'sort_order' => 20,
            ],
        );

        Status::query()->firstOrCreate(
            ['slug' => 'released'],
            [
                'name' => 'Released',
                'description' => 'Asset has left storage and is no longer billable.',
                'is_billable' => false,
                'is_active' => true,
                'sort_order' => 30,
            ],
        );
    }
}
