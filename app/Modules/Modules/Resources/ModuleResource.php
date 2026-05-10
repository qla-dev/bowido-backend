<?php

namespace App\Modules\Modules\Resources;

use App\Modules\RolePermissions\Resources\RolePermissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'role_permissions' => RolePermissionResource::collection($this->whenLoaded('rolePermissions')),
        ];
    }
}
