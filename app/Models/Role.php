<?php

namespace App\Models;

use App\Models\RolePermission;
use App\Models\User;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }
}
