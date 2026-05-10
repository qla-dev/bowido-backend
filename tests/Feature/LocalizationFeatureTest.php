<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_error_is_translated_to_bosnian(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ], [
            'X-Locale' => 'bs',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Neispravni pristupni podaci.');
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
