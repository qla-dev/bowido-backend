<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\Models\ApiToken;
use App\Modules\Auth\Repositories\ApiTokenRepository;
use App\Modules\Users\Repositories\UserRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        return DB::transaction(function () use ($user, $data): array {
            $plainTextToken = Str::random(80);
            $expiresAt = now()->addMinutes((int) env('API_TOKEN_TTL_MINUTES', 10080));

            $apiToken = $this->apiTokenRepository->create(
                user: $user,
                name: $data->tokenName,
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
}
