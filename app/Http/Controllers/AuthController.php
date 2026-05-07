<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'token_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->with(['role', 'customerDetail'])
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $result = $this->issueToken($user, (string) ($validated['token_name'] ?? 'api-token'));

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => $this->serializeUser($result['user']),
        ], 'Login successful.');
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:255', 'unique:users,phone_number'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'token_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $defaultRole = Role::query()
            ->where('name', 'user')
            ->where('is_active', true)
            ->first();

        if (! $defaultRole instanceof Role) {
            throw ValidationException::withMessages([
                'role' => ['Default user registration is currently unavailable.'],
            ]);
        }

        $user = DB::transaction(function () use ($defaultRole, $validated): User {
            /** @var User $user */
            $user = User::query()->create([
                'role_id' => $defaultRole->id,
                'name' => trim((string) $validated['name']),
                'email' => strtolower((string) $validated['email']),
                'phone_number' => User::normalizePhoneNumber($validated['phone_number'] ?? null),
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            return $user->load(['role', 'customerDetail']);
        });

        $result = $this->issueToken($user, (string) ($validated['token_name'] ?? 'registration-token'));

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => $this->serializeUser($result['user']),
        ], 'Registration successful.', status: 201);
    }

    public function me(Request $request): JsonResponse
    {
        $request->user()->loadMissing(['role', 'customerDetail']);

        return $this->success(
            $this->serializeUser($request->user()),
            'Authenticated user retrieved successfully.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $apiToken */
        $apiToken = $request->attributes->get('currentApiToken');

        if (! $apiToken instanceof ApiToken) {
            $plainTextToken = $request->bearerToken();

            if (is_string($plainTextToken) && $plainTextToken !== '') {
                $apiToken = ApiToken::query()
                    ->where('token', hash('sha256', $plainTextToken))
                    ->first();
            }
        }

        if ($apiToken instanceof ApiToken) {
            $apiToken->delete();
        }

        return $this->success(null, 'Logout successful.');
    }

    /**
     * @return array{token: string, expires_at: string|null, user: \App\Models\User}
     */
    private function issueToken(User $user, string $tokenName): array
    {
        return DB::transaction(function () use ($user, $tokenName): array {
            $plainTextToken = Str::random(80);
            $expiresAt = now()->addMinutes((int) env('API_TOKEN_TTL_MINUTES', 10080));

            $apiToken = ApiToken::query()->create([
                'user_id' => $user->id,
                'name' => $tokenName,
                'token' => hash('sha256', $plainTextToken),
                'expires_at' => $expiresAt,
            ]);

            $user->forceFill(['last_login_at' => now()])->save();

            return [
                'token' => $plainTextToken,
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'user' => $user->fresh(['role', 'customerDetail']),
            ];
        });
    }
}