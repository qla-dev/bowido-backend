<?php

namespace App\Providers;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\AuditLogs\Policies\AuditLogPolicy;
use App\Modules\Auth\Models\ApiToken;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\CustomerDetails\Policies\CustomerDetailPolicy;
use App\Modules\GhostPalletReports\Models\GhostPalletReport;
use App\Modules\GhostPalletReports\Policies\GhostPalletReportPolicy;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\InvoiceItems\Policies\InvoiceItemPolicy;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Policies\InvoicePolicy;
use App\Modules\Modules\Models\Module;
use App\Modules\Modules\Policies\ModulePolicy;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Pallets\Policies\PalletPolicy;
use App\Modules\RolePermissions\Models\RolePermission;
use App\Modules\RolePermissions\Policies\RolePermissionPolicy;
use App\Modules\Roles\Models\Role;
use App\Modules\Roles\Policies\RolePolicy;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\ServiceReports\Policies\ServiceReportPolicy;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Policies\StatusPolicy;
use App\Modules\Users\Models\User;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::viaRequest('api-token', function (Request $request): ?User {
            $plainTextToken = $request->bearerToken();

            if (! is_string($plainTextToken) || $plainTextToken === '') {
                return null;
            }

            $token = ApiToken::query()
                ->with(['user.role', 'user.customerDetail'])
                ->where('token', hash('sha256', $plainTextToken))
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (! $token || ! $token->user || ! $token->user->is_active) {
                return null;
            }

            $token->forceFill(['last_used_at' => now()])->saveQuietly();

            $request->attributes->set('currentApiToken', $token);

            return $token->user;
        });

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(CustomerDetail::class, CustomerDetailPolicy::class);
        Gate::policy(Status::class, StatusPolicy::class);
        Gate::policy(Pallet::class, PalletPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(ServiceReport::class, ServiceReportPolicy::class);
        Gate::policy(GhostPalletReport::class, GhostPalletReportPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(InvoiceItem::class, InvoiceItemPolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(RolePermission::class, RolePermissionPolicy::class);
    }
}
