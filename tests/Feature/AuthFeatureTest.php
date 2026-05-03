<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\ApiToken;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_fetch_profile_and_logout(): void
    {
        $user = $this->makeUser('admin', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'token_name' => 'feature-test',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_at', 'user'],
                'message',
                'meta',
                'errors',
            ]);

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->makeUser('admin', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('api_tokens', [
            'user_id' => $user->id,
        ]);
    }
}
