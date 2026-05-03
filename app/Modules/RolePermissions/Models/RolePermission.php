<?php

namespace App\Modules\RolePermissions\Models;

use App\Modules\Modules\Models\Module;
use App\Modules\Roles\Models\Role;
use Database\Factories\RolePermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    /** @use HasFactory<RolePermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'role_id',
        'module_id',
        'can_list',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
    ];

    protected function casts(): array
    {
        return [
            'can_list' => 'boolean',
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    protected static function newFactory(): RolePermissionFactory
    {
        return RolePermissionFactory::new();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
