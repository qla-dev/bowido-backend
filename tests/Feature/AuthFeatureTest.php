<?php

namespace Tests\Feature;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_without_creating_api_token(): void
    {
        $admin = $this->makeUser('admin');
        $role = $this->role('operator');

        $registerResponse = $this->actingAs($admin, 'api')->postJson('/api/auth/register', [
            'role_id' => $role->id,
            'name' => 'Limited User',
            'email' => 'user@example.com',
            'phone_number' => '+387 61 123 456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('data.email', 'user@example.com')
            ->assertJsonPath('data.role.name', 'operator')
            ->assertJsonPath('data.phone_number', '+38761123456')
            ->assertJsonStructure([
                'data' => ['id', 'role_id', 'name', 'email', 'phone_number', 'is_active', 'role', 'customer_detail'],
                'message',
                'meta',
                'errors',
            ]);

        $registerData = $registerResponse->json('data');

        $this->assertIsArray($registerData);
        $this->assertArrayNotHasKey('token', $registerData);
        $this->assertArrayNotHasKey('created_at', $registerData);
        $this->assertArrayNotHasKey('updated_at', $registerData);
        $this->assertStringStartsWith('{"message":"Registration successful.","data":', $registerResponse->getContent());

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_guest_cannot_register_user(): void
    {
        $role = $this->role('operator');

        $this->postJson('/api/auth/register', [
            'role_id' => $role->id,
            'name' => 'Limited User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_user_can_login_create_session_and_token_fetch_profile_and_logout(): void
    {
        config(['session.driver' => 'database']);

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
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.role.name', 'admin')
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_at', 'session_expires_at', 'user'],
                'message',
                'meta',
                'errors',
            ]);

        $rolePermissions = $loginResponse->json('data.user.role.role_permissions');
        $userData = $loginResponse->json('data.user');
        $token = $loginResponse->json('data.token');
        $tokenExpiresAt = $loginResponse->json('data.expires_at');
        $sessionExpiresAt = $loginResponse->json('data.session_expires_at');
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $sessionCookieName = config('session.cookie');
        $sessionCookieValue = $sessionCookie?->getValue();

        $this->assertIsArray($rolePermissions);
        $this->assertNotEmpty($rolePermissions);
        $this->assertIsArray($userData);
        $this->assertArrayNotHasKey('created_at', $userData);
        $this->assertArrayNotHasKey('updated_at', $userData);
        $this->assertStringStartsWith('{"message":"Login successful.","data":', $loginResponse->getContent());
        $this->assertNotNull($token);
        $this->assertNotNull($tokenExpiresAt);
        $this->assertNotNull($sessionExpiresAt);
        $this->assertNotNull($sessionCookie);
        $this->assertNotNull($sessionCookieValue);
        $this->assertEqualsWithDelta(
            now()->addMinutes(config('session.lifetime'))->getTimestamp(),
            strtotime($tokenExpiresAt),
            120,
        );
        $this->assertEqualsWithDelta(
            now()->addMinutes(config('session.lifetime'))->getTimestamp(),
            strtotime($sessionExpiresAt),
            120,
        );
        $this->assertEqualsWithDelta(
            now()->addMinutes(config('session.lifetime'))->getTimestamp(),
            $sessionCookie->getExpiresTime(),
            120,
        );
        $loginResponse->assertSessionHas('auth_token', $token);
        $loginResponse->assertSessionHas('auth_token_expires_at', $tokenExpiresAt);
        $loginResponse->assertSessionHas(
            app('auth')->guard('web')->getName(),
            (string) $user->getAuthIdentifier(),
        );

        app('auth')->forgetGuards();

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [$sessionCookieName => $sessionCookieValue],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');

        app('auth')->forgetGuards();

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');

        app('auth')->forgetGuards();

        $this->call(
            'POST',
            '/api/auth/logout',
            [],
            [$sessionCookieName => $sessionCookieValue],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        app('auth')->forgetGuards();

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [$sessionCookieName => $sessionCookieValue],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        )
            ->assertUnauthorized();

        app('auth')->forgetGuards();

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        )
            ->assertUnauthorized();

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_user_can_use_login_token_without_session_cookie(): void
    {
        app('auth')->forgetGuards();

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
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.role.name', 'admin')
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_at', 'session_expires_at', 'user'],
                'message',
                'meta',
                'errors',
            ]);

        $token = $loginResponse->json('data.token');
        $tokenExpiresAt = $loginResponse->json('data.expires_at');
        $this->assertNotNull($token);
        $this->assertNotNull($tokenExpiresAt);
        $this->assertDatabaseCount('api_tokens', 1);

        app('auth')->forgetGuards();

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');

        app('auth')->forgetGuards();

        $this->call(
            'POST',
            '/api/auth/logout',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        )
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        app('auth')->forgetGuards();

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        )
            ->assertUnauthorized();

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_frontend_token_only_login_skips_web_session(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser('admin', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this
            ->withHeader('X-Trackpal-Token-Only', 'true')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
                'token_name' => 'feature-test',
            ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.session_expires_at', null)
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.role.name', 'admin');

        $token = $loginResponse->json('data.token');
        $roleData = $loginResponse->json('data.user.role');

        $this->assertNotNull($token);
        $this->assertNull($loginResponse->getCookie(config('session.cookie')));
        $this->assertIsArray($roleData);
        $this->assertArrayNotHasKey('role_permissions', $roleData);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('api_tokens', 1);

        app('auth')->forgetGuards();

        $profileResponse = $this->call(
            'GET',
            '/api/auth/me',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_X_TRACKPAL_TOKEN_ONLY' => 'true',
            ],
        );

        $profileResponse
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');

        $profileRoleData = $profileResponse->json('data.role');

        $this->assertIsArray($profileRoleData);
        $this->assertArrayNotHasKey('role_permissions', $profileRoleData);
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_customer_can_login_with_kvk_number(): void
    {
        $user = $this->makeUser('customer', [
            'email' => 'customer@example.com',
            'password' => 'password123',
        ]);

        CustomerDetail::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'Example Customer',
            'kvk' => '1234-5678',
            'is_active' => true,
        ]);

        $loginResponse = $this
            ->withHeader('X-Trackpal-Token-Only', 'true')
            ->postJson('/api/auth/login', [
                'login_type' => 'customer',
                'kvk' => '12345678',
                'password' => 'password123',
                'token_name' => 'feature-test',
            ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.session_expires_at', null)
            ->assertJsonPath('data.user.email', 'customer@example.com')
            ->assertJsonPath('data.user.customer_detail.kvk_number', '1234-5678');

        $this->assertNotNull($loginResponse->json('data.token'));
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_customer_with_shared_kvk_must_choose_company_before_login(): void
    {
        $firstUser = $this->makeUser('customer', [
            'email' => 'bouwmaat-dordrecht@example.com',
            'password' => 'first-password',
        ]);
        $secondUser = $this->makeUser('customer', [
            'email' => 'bme-bouwmaten@example.com',
            'password' => 'second-password',
        ]);

        $firstDetail = CustomerDetail::factory()->create([
            'user_id' => $firstUser->id,
            'company_name' => 'Bouwmaat Dordrecht',
            'kvk' => '24172907',
            'is_active' => true,
        ]);
        $secondDetail = CustomerDetail::factory()->create([
            'user_id' => $secondUser->id,
            'company_name' => 'BME Bouwmaten Nederland B.V.',
            'kvk' => '24172907',
            'is_active' => true,
        ]);

        $selectionResponse = $this
            ->withHeader('X-Trackpal-Token-Only', 'true')
            ->postJson('/api/auth/login', [
                'login_type' => 'customer',
                'kvk' => '24.172.907',
                'password' => 'second-password',
                'token_name' => 'feature-test',
            ]);

        $selectionResponse
            ->assertStatus(409)
            ->assertJsonPath('data.code', 'company_selection_required')
            ->assertJsonCount(2, 'data.companies')
            ->assertJsonFragment([
                'customer_detail_id' => $firstDetail->id,
                'company_name' => 'Bouwmaat Dordrecht',
            ])
            ->assertJsonFragment([
                'customer_detail_id' => $secondDetail->id,
                'company_name' => 'BME Bouwmaten Nederland B.V.',
            ]);

        $this->assertDatabaseCount('api_tokens', 0);

        $loginResponse = $this
            ->withHeader('X-Trackpal-Token-Only', 'true')
            ->postJson('/api/auth/login', [
                'login_type' => 'customer',
                'kvk' => '24172907',
                'customer_detail_id' => $secondDetail->id,
                'password' => 'second-password',
                'token_name' => 'feature-test',
            ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.user.email', 'bme-bouwmaten@example.com')
            ->assertJsonPath('data.user.customer_detail.company_name', 'BME Bouwmaten Nederland B.V.');

        $this->assertNotNull($loginResponse->json('data.token'));
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $authLogPath = storage_path('logs/laravel.log');

        if (file_exists($authLogPath)) {
            unlink($authLogPath);
        }

        $user = $this->makeUser('admin', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertStringStartsWith('{"message":"Email or password are incorrect.","data":null,', $response->getContent());

        $this->assertDatabaseMissing('api_tokens', [
            'user_id' => $user->id,
        ]);

        $this->assertFileExists($authLogPath);

        $authLogContent = file_get_contents($authLogPath);

        $this->assertIsString($authLogContent);
        $this->assertStringContainsString('Auth login failed: password mismatch.', $authLogContent);
        $this->assertStringNotContainsString('wrong-password', $authLogContent);
        $this->assertStringNotContainsString($user->email, $authLogContent);
    }

    public function test_registration_requires_unique_email(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('user', [
            'email' => 'user@example.com',
        ]);

        $this->actingAs($admin, 'api')->postJson('/api/auth/register', [
            'role_id' => $this->role('user')->id,
            'name' => 'Duplicate User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
