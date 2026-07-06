<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\Models\ApiToken;
use App\Modules\Auth\Repositories\ApiTokenRepository;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const SESSION_AUTH_TOKEN_KEY = 'auth_token';

    private const SESSION_AUTH_TOKEN_EXPIRES_AT_KEY = 'auth_token_expires_at';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApiTokenRepository $apiTokenRepository,
    ) {
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string|null, session_expires_at: string|null, user: \App\Modules\Users\Models\User}
     */
    public function login(LoginData $data, Request $request): array
    {
        $user = $this->userRepository->findByEmailForAuth($data->email);

        if (! $user || ! $user->is_active || ! Hash::check($data->password, $user->password)) {
            throw new AuthenticationException(__('Invalid credentials.'));
        }

        $result = $this->issueToken($user, $data->tokenName);

        $shouldStartSession = $this->shouldStartSession($request);

        if ($shouldStartSession) {
            $result['user']->loadMissing(['role.rolePermissions.module', 'customerDetail']);

            $this->startSession(
                request: $request,
                user: $result['user'],
                plainTextToken: $result['token'],
                tokenExpiresAt: $result['expires_at'],
            );
        }

        return [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'session_expires_at' => $shouldStartSession ? $this->sessionExpiresAt() : null,
            'user' => $result['user'],
        ];
    }

    public function register(RegisterData $data): User
    {
        $role = Role::query()
            ->whereKey($data->roleId)
            ->where('is_active', true)
            ->first();

        if (! $role instanceof Role) {
            throw ValidationException::withMessages([
                'role_id' => [__('Selected role is invalid or inactive.')],
            ]);
        }

        return DB::transaction(function () use ($role, $data): User {
            /** @var User $user */
            $user = $this->userRepository->create([
                'role_id' => $role->id,
                'name' => $data->name,
                'email' => $data->email,
                'phone_number' => $data->phoneNumber,
                'password' => $data->password,
                'is_active' => true,
            ]);

            $user->load(['role.rolePermissions.module', 'customerDetail']);

            return $user;
        });
    }

    public function logout(Request $request): void
    {
        $hashedTokens = [];

        /** @var ApiToken|null $currentApiToken */
        $currentApiToken = $request->attributes->get('currentApiToken');

        if ($currentApiToken instanceof ApiToken) {
            $hashedTokens[] = $currentApiToken->token;
        }

        $bearerToken = $request->bearerToken();

        if (is_string($bearerToken) && $bearerToken !== '') {
            $hashedTokens[] = hash('sha256', $bearerToken);
        }

        if ($request->hasSession()) {
            $hasSessionContext = Auth::guard('web')->check()
                || $request->session()->has(self::SESSION_AUTH_TOKEN_KEY)
                || $request->cookies->has((string) config('session.cookie'));

            if ($hasSessionContext) {
                $sessionToken = $request->session()->get(self::SESSION_AUTH_TOKEN_KEY);

                if (is_string($sessionToken) && $sessionToken !== '') {
                    $hashedTokens[] = hash('sha256', $sessionToken);
                }

                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }

                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        foreach (array_unique($hashedTokens) as $hashedToken) {
            $apiToken = $this->apiTokenRepository->findByHashedToken($hashedToken);

            if ($apiToken instanceof ApiToken) {
                $this->apiTokenRepository->delete($apiToken);
            }
        }
    }

    /**
     * @return array{token: string, expires_at: string|null, user: \App\Modules\Users\Models\User}
     */
    private function issueToken(User $user, string $tokenName): array
    {
        return DB::transaction(function () use ($user, $tokenName): array {
            $plainTextToken = Str::random(80);
            $expiresAt = now()->addMinutes((int) env('API_TOKEN_TTL_MINUTES', config('session.lifetime')));

            $apiToken = $this->apiTokenRepository->create(
                user: $user,
                name: $tokenName,
                hashedToken: hash('sha256', $plainTextToken),
                expiresAt: $expiresAt,
            );

            $authenticatedUser = $this->markUserLoggedIn($user);

            return [
                'token' => $plainTextToken,
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'user' => $authenticatedUser,
            ];
        });
    }

    private function startSession(Request $request, User $user, string $plainTextToken, ?string $tokenExpiresAt): void
    {
        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_AUTH_TOKEN_KEY, $plainTextToken);
        $request->session()->put(self::SESSION_AUTH_TOKEN_EXPIRES_AT_KEY, $tokenExpiresAt);
    }

    private function markUserLoggedIn(User $user): User
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->loadMissing(['role', 'customerDetail']);
    }

    private function shouldStartSession(Request $request): bool
    {
        $tokenOnly = strtolower((string) $request->headers->get('X-Trackpal-Token-Only', ''));

        return ! in_array($tokenOnly, ['1', 'true', 'yes'], true);
    }

    private function sessionExpiresAt(): string
    {
        return now()->addMinutes((int) config('session.lifetime'))->toIso8601String();
    }
}
