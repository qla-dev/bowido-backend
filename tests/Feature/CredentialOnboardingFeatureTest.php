<?php

namespace Tests\Feature;

use App\Modules\Shared\Enums\ModuleKey;
use App\Modules\Users\Mail\TrackpalCredentialsMail;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CredentialOnboardingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_customer_receives_a_hashed_sixteen_character_temporary_password(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin, 'api')->postJson('/api/users', [
            'role_id' => $this->role('customer')->id,
            'name' => 'Nieuwe Klant B.V.',
            'email' => 'nieuwe-klant@example.com',
            'customer_details' => [
                'company_name' => 'Nieuwe Klant B.V.',
                'kvk' => '12345678',
                'billing_email' => 'nieuwe-klant@example.com',
                'default_price_per_day' => 2,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.first_time_login', true)
            ->assertJsonPath('data.credential_email_sent', true);

        $user = User::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($user->first_time_login);

        Mail::assertSent(TrackpalCredentialsMail::class, function (TrackpalCredentialsMail $mail) use ($user): bool {
            $this->assertSame(16, strlen($mail->temporaryPassword));
            $this->assertTrue(Hash::check($mail->temporaryPassword, $user->password));
            $this->assertNotSame($mail->temporaryPassword, $user->password);
            $body = $mail->render();
            $this->assertStringContainsString('12345678', $body);
            $this->assertStringContainsString(e($mail->temporaryPassword), $body);

            return $mail->hasTo('nieuwe-klant@example.com');
        });
    }

    public function test_bulk_distribution_resets_only_selected_users_with_different_passwords(): void
    {
        Mail::fake();
        $admin = $this->makeUser('admin');
        $first = $this->makeUser('customer', ['password' => 'old-first', 'first_time_login' => false]);
        $second = $this->makeUser('driver', ['password' => 'old-second', 'first_time_login' => false]);
        $untouched = $this->makeUser('technician', ['password' => 'old-third', 'first_time_login' => false]);
        $passwords = [];

        $this->actingAs($admin, 'api')
            ->postJson('/api/users/send-login-details', ['user_ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonCount(2, 'data.sent')
            ->assertJsonCount(0, 'data.failed');

        Mail::assertSent(TrackpalCredentialsMail::class, 2);
        Mail::assertSent(TrackpalCredentialsMail::class, function (TrackpalCredentialsMail $mail) use (&$passwords): bool {
            $passwords[$mail->credentialUser->id] = $mail->temporaryPassword;

            return true;
        });

        $this->assertCount(2, array_unique($passwords));
        $this->assertSame(16, strlen($passwords[$first->id]));
        $this->assertSame(16, strlen($passwords[$second->id]));
        $this->assertTrue(Hash::check($passwords[$first->id], $first->fresh()->password));
        $this->assertTrue(Hash::check($passwords[$second->id], $second->fresh()->password));
        $this->assertTrue($first->fresh()->first_time_login);
        $this->assertTrue($second->fresh()->first_time_login);
        $this->assertTrue(Hash::check('old-third', $untouched->fresh()->password));
        $this->assertFalse($untouched->fresh()->first_time_login);
    }

    public function test_creation_preserves_the_user_and_returns_a_warning_when_email_fails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin, 'api')->postJson('/api/users', [
            'role_id' => $this->role('driver')->id,
            'name' => 'New Driver',
            'email' => 'new-driver@example.com',
        ])->assertCreated()
            ->assertJsonPath('data.credential_email_sent', false);

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'email' => 'new-driver@example.com',
            'first_time_login' => true,
        ]);
    }

    public function test_failed_manual_email_keeps_the_existing_password_and_onboarding_state(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('driver', ['password' => 'old-password', 'first_time_login' => false]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/users/send-login-details', ['user_ids' => [$target->id]])
            ->assertOk()
            ->assertJsonCount(0, 'data.sent')
            ->assertJsonCount(1, 'data.failed');

        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
        $this->assertFalse($target->fresh()->first_time_login);
    }

    public function test_guest_can_request_a_temporary_password_by_email_without_exposing_account_existence(): void
    {
        Mail::fake();
        $user = $this->makeUser('driver', [
            'email' => 'forgot-password@example.com',
            'password' => 'old-password',
            'first_time_login' => false,
        ]);
        $oldToken = $this->withHeader('X-Trackpal-Token-Only', 'true')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'old-password',
            ])
            ->assertOk()
            ->json('data.token');

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an active account matches this email address, a new temporary password has been sent.');

        Mail::assertSent(TrackpalCredentialsMail::class, function (TrackpalCredentialsMail $mail) use ($user): bool {
            $this->assertTrue($mail->isPasswordReset);
            $this->assertTrue(Hash::check($mail->temporaryPassword, $user->fresh()->password));
            $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
            $this->assertTrue($user->fresh()->first_time_login);

            return $mail->hasTo($user->email);
        });

        $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an active account matches this email address, a new temporary password has been sent.');

        Mail::assertSent(TrackpalCredentialsMail::class, 1);
    }

    public function test_only_admins_can_distribute_credentials(): void
    {
        $operatorRole = $this->role('operator');
        $this->grantPermissions($operatorRole, [ModuleKey::Users->value], ['can_update' => true]);
        $operator = $this->makeUser('operator');
        $target = $this->makeUser('customer');

        $this->actingAs($operator, 'api')
            ->postJson('/api/users/send-login-details', ['user_ids' => [$target->id]])
            ->assertForbidden();
    }

    public function test_first_login_password_change_is_atomic_and_clears_the_flag_only_on_success(): void
    {
        $user = $this->makeUser('customer', ['password' => 'temporary-password', 'first_time_login' => true]);

        $this->actingAs($user, 'api')
            ->putJson('/api/auth/first-login/password', [
                'password' => 'new-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertUnprocessable();

        $this->assertTrue($user->fresh()->first_time_login);
        $this->assertTrue(Hash::check('temporary-password', $user->fresh()->password));

        $this->actingAs($user, 'api')
            ->putJson('/api/auth/first-login/password', [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_time_login', false);

        $this->assertFalse($user->fresh()->first_time_login);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_first_login_user_can_explicitly_keep_the_assigned_password(): void
    {
        $user = $this->makeUser('driver', ['password' => 'temporary-password', 'first_time_login' => true]);

        $this->actingAs($user, 'api')
            ->postJson('/api/auth/first-login/keep-password')
            ->assertOk()
            ->assertJsonPath('data.first_time_login', false);

        $this->assertTrue(Hash::check('temporary-password', $user->fresh()->password));
        $this->assertFalse($user->fresh()->first_time_login);
    }
}
