<?php

namespace App\Providers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::viaRequest('api-token', function (Request $request): ?User {
            $plainTextToken = $request->bearerToken();

            if (! is_string($plainTextToken) || $plainTextToken === '') {
                return null;
            }

            $token = ApiToken::query()
                ->with(['user.role.rolePermissions.module', 'user.customerDetail'])
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
    }
}