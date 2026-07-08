<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_failure_message_uses_locale_header(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ], [
            'X-Locale' => 'bs',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Email ili lozinka nisu ispravni.');

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ], [
            'X-Locale' => 'nl',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'E-mail of wachtwoord is onjuist.');
    }

    public function test_accept_language_header_switches_to_dutch(): void
    {
        $role = $this->role('operator');

        $this->postJson('/api/auth/register', [
            'role_id' => $role->id,
            'name' => 'Limited User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'Accept-Language' => 'nl-NL',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Niet geauthenticeerd.');
    }
}
