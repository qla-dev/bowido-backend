<?php

namespace App\Modules\Roles\Resources;

use App\Modules\RolePermissions\Resources\RolePermissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'module_ids' => $this->whenLoaded(
                'rolePermissions',
                fn (): array => $this->rolePermissions->pluck('module_id')->sort()->values()->all(),
            ),
            'role_permissions' => RolePermissionResource::collection($this->whenLoaded('rolePermissions')),
        ];
    }
}
