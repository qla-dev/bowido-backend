<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_fetch_profile_and_logout(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Limited User',
            'email' => 'user@example.com',
            'phone_number' => '+387 61 123 456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'token_name' => 'registration-test',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'user@example.com')
            ->assertJsonPath('data.user.role.name', 'user')
            ->assertJsonPath('data.user.phone_number', '+38761123456')
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_at', 'user'],
                'message',
                'meta',
                'errors',
            ]);

        $token = $registerResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'user@example.com');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('api_tokens', 0);
    }

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

    public function test_registration_requires_unique_email(): void
    {
        $this->makeUser('user', [
            'email' => 'user@example.com',
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Duplicate User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
