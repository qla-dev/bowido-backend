<?php

namespace App\Modules\Roles\DTOs;

use Illuminate\Support\Str;

readonly class RoleData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
        public ?array $moduleIds,
        public ?array $rolePermissions,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: Str::of((string) $attributes['name'])->squish()->lower()->value(),
            description: $attributes['description'] ?? null,
            isActive: (bool) ($attributes['is_active'] ?? true),
            moduleIds: array_key_exists('module_ids', $attributes)
                ? array_values(array_unique(array_map('intval', (array) $attributes['module_ids'])))
                : null,
            rolePermissions: self::normalizeRolePermissions($attributes),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>|null
     */
    private static function normalizeRolePermissions(array $attributes): ?array
    {
        if (! array_key_exists('role_permissions', $attributes)) {
            return null;
        }

        $permissionsByModule = [];

        foreach ((array) $attributes['role_permissions'] as $permission) {
            if (! is_array($permission)) {
                continue;
            }

            $moduleId = (int) ($permission['module_id'] ?? 0);

            if ($moduleId <= 0) {
                continue;
            }

            $permissionsByModule[$moduleId] = [
                'module_id' => $moduleId,
                'can_list' => (bool) ($permission['can_list'] ?? false),
                'can_view' => (bool) ($permission['can_view'] ?? false),
                'can_create' => (bool) ($permission['can_create'] ?? false),
                'can_update' => (bool) ($permission['can_update'] ?? false),
                'can_delete' => (bool) ($permission['can_delete'] ?? false),
            ];
        }

        return array_values($permissionsByModule);
    }
}
