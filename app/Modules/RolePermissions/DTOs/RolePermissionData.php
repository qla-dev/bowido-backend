<?php

namespace App\Modules\RolePermissions\DTOs;

readonly class RolePermissionData
{
    public function __construct(
        public int $roleId,
        public int $moduleId,
        public bool $canList,
        public bool $canView,
        public bool $canCreate,
        public bool $canUpdate,
        public bool $canDelete,
        public ?string $scope,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            roleId: (int) $attributes['role_id'],
            moduleId: (int) $attributes['module_id'],
            canList: (bool) ($attributes['can_list'] ?? false),
            canView: (bool) ($attributes['can_view'] ?? false),
            canCreate: (bool) ($attributes['can_create'] ?? false),
            canUpdate: (bool) ($attributes['can_update'] ?? false),
            canDelete: (bool) ($attributes['can_delete'] ?? false),
            scope: isset($attributes['scope']) ? (string) $attributes['scope'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role_id' => $this->roleId,
            'module_id' => $this->moduleId,
            'can_list' => $this->canList,
            'can_view' => $this->canView,
            'can_create' => $this->canCreate,
            'can_update' => $this->canUpdate,
            'can_delete' => $this->canDelete,
            'scope' => $this->scope,
        ];
    }
}
