<?php

namespace App\Modules\Users\Models;

use App\Modules\Auth\Models\ApiToken;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Roles\Models\Role;
use App\Modules\ServiceReports\Models\ServiceReport;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function customerDetail(): \Illuminate\Database\Eloquent\Relations\HasOne
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
        $this->loadMissing('role');

        if (! $this->is_active || ! $this->role || ! $this->role->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        $this->loadMissing('role.rolePermissions.module');

        $permission = $this->role->rolePermissions
            ->first(fn ($item) => $item->module?->slug === $moduleSlug);

        if (! $permission) {
            return false;
        }

        return match ($ability) {
            'viewAny' => $permission->can_list,
            'view' => $permission->can_view,
            'create' => $permission->can_create,
            'update' => $permission->can_update,
            'delete' => $permission->can_delete,
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
}
