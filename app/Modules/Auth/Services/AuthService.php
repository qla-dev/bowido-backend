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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApiTokenRepository $apiTokenRepository,
    ) {
    }

    /**
     * @return array{token: string, expires_at: string|null, user: \App\Modules\Users\Models\User}
     */
    public function login(LoginData $data): array
    {
        $user = $this->userRepository->findByEmailForAuth($data->email);

        if (! $user || ! $user->is_active || ! Hash::check($data->password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->issueToken($user, $data->tokenName);
    }

    /**
     * @return array{token: string, expires_at: string|null, user: \App\Modules\Users\Models\User}
     */
    public function register(RegisterData $data): array
    {
        $defaultRole = Role::query()
            ->where('name', 'user')
            ->where('is_active', true)
            ->first();

        if (! $defaultRole instanceof Role) {
            throw ValidationException::withMessages([
                'role' => ['Default user registration is currently unavailable.'],
            ]);
        }

        return DB::transaction(function () use ($defaultRole, $data): array {
            /** @var User $user */
            $user = $this->userRepository->create([
                'role_id' => $defaultRole->id,
                'name' => $data->name,
                'email' => $data->email,
                'phone_number' => $data->phoneNumber,
                'password' => $data->password,
                'is_active' => true,
            ]);

            return $this->issueToken($user->fresh(['role', 'customerDetail']), $data->tokenName);
        });
    }

    public function logout(Request $request): void
    {
        /** @var ApiToken|null $apiToken */
        $apiToken = $request->attributes->get('currentApiToken');

        if (! $apiToken instanceof ApiToken) {
            $plainTextToken = $request->bearerToken();

            if (is_string($plainTextToken) && $plainTextToken !== '') {
                $apiToken = $this->apiTokenRepository->findByHashedToken(hash('sha256', $plainTextToken));
            }
        }

        if ($apiToken instanceof ApiToken) {
            $this->apiTokenRepository->delete($apiToken);
        }
    }

    /**
     * @return array{token: string, expires_at: string|null, user: \App\Modules\Users\Models\User}
     */
    private function issueToken(User $user, string $tokenName): array
    {
        return DB::transaction(function () use ($user, $tokenName): array {
            $plainTextToken = Str::random(80);
            $expiresAt = now()->addMinutes((int) env('API_TOKEN_TTL_MINUTES', 10080));

            $apiToken = $this->apiTokenRepository->create(
                user: $user,
                name: $tokenName,
                hashedToken: hash('sha256', $plainTextToken),
                expiresAt: $expiresAt,
            );

            $user->forceFill(['last_login_at' => now()])->save();

            return [
                'token' => $plainTextToken,
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'user' => $user->fresh(['role', 'customerDetail']),
            ];
        });
    }
}
