<?php

namespace App\Modules\Users\Resources;

use App\Modules\CustomerDetails\Resources\CustomerDetailResource;
use App\Modules\Roles\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'role_name' => $this->whenLoaded('role', fn (): ?string => $this->role?->name),
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'role' => new RoleResource($this->whenLoaded('role')),
            'permission_codes' => $this->whenLoaded('role', fn (): array => $this->isAdmin()
                ? ['*']
                : $this->role->rolePermissions
                    ->filter(fn ($permission): bool => $permission->can_list || $permission->can_view)
                    ->map(fn ($permission): ?string => $permission->module?->slug)
                    ->filter()
                    ->values()
                    ->all()),
            'customer_detail' => new CustomerDetailResource($this->whenLoaded('customerDetail')),
        ];
    }
}
