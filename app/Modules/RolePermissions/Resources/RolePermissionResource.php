<?php

namespace App\Modules\RolePermissions\Resources;

use App\Modules\Modules\Resources\ModuleResource;
use App\Modules\Roles\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RolePermissionResource extends JsonResource
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
            'role_id' => $this->role_id,
            'module_id' => $this->module_id,
            'can_list' => $this->can_list,
            'can_view' => $this->can_view,
            'can_create' => $this->can_create,
            'can_update' => $this->can_update,
            'can_delete' => $this->can_delete,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role' => new RoleResource($this->whenLoaded('role')),
            'module' => new ModuleResource($this->whenLoaded('module')),
        ];
    }
}
