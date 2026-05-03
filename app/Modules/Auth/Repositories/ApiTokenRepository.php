<?php

namespace App\Modules\Auth\Repositories;

use App\Modules\Auth\Models\ApiToken;
use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;

class ApiTokenRepository
{
    public function create(User $user, string $name, string $hashedToken, ?CarbonInterface $expiresAt = null): ApiToken
    {
        return ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $hashedToken,
            'expires_at' => $expiresAt,
        ]);
    }

    public function delete(ApiToken $apiToken): void
    {
        $apiToken->delete();
    }

    public function findByHashedToken(string $hashedToken): ?ApiToken
    {
        return ApiToken::query()->where('token', $hashedToken)->first();
    }
}
