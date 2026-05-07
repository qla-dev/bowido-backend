<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone_number',
        'password',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function customerDetail(): HasOne
    {
        return $this->hasOne(CustomerDetail::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function auditLogsMade(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'made_by_user_id');
    }

    public function serviceReportsCreated(): HasMany
    {
        return $this->hasMany(ServiceReport::class, 'reported_by_user_id');
    }

    public function serviceReportsResolved(): HasMany
    {
        return $this->hasMany(ServiceReport::class, 'resolved_by_user_id');
    }

    public function ghostPalletReports(): HasMany
    {
        return $this->hasMany(GhostPalletReport::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function hasModulePermission(string $moduleSlug, string $ability): bool
    {
        $this->loadMissing('role.rolePermissions.module');

        if (! $this->is_active || ! $this->role || ! $this->role->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $permission = $this->role->rolePermissions
            ->first(fn (RolePermission $item) => $item->module?->slug === $moduleSlug && $item->module?->is_active);

        if (! $permission) {
            return false;
        }

        return match ($ability) {
            'viewAny', 'list', 'index' => $permission->can_list,
            'view', 'show' => $permission->can_view,
            'create', 'store' => $permission->can_create,
            'update' => $permission->can_update,
            'delete', 'destroy' => $permission->can_delete,
            default => false,
        };
    }

    public function isAdmin(): bool
    {
        return strtolower((string) $this->role?->name) === 'admin';
    }

    public function isCustomer(): bool
    {
        return strtolower((string) $this->role?->name) === 'customer';
    }

    public static function normalizePhoneNumber(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = preg_replace('/(?!^\+)[^\d]/', '', trim($value)) ?? '';

        return $trimmed !== '' ? $trimmed : null;
    }
}