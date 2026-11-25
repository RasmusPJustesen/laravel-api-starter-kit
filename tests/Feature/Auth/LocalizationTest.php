<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_language_translates_messages_without_changing_error_codes(): void
    {
        config(['starter-kit.locales' => ['en', 'da']]);
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->withHeader('Accept-Language', 'da-DK,da;q=0.9,en;q=0.8')
            ->deleteJson('/user/sessions/others')
            ->assertForbidden()
            ->assertHeader('Content-Language', 'da')
            ->assertJsonPath('error_details.0.code', 'PASSWORD_CONFIRMATION_REQUIRED')
            ->assertJsonPath('message', 'Bekræft venligst din adgangskode.');

        $this->withHeader('Accept-Language', 'fr')
            ->deleteJson('/user/sessions/others')
            ->assertForbidden()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', 'Please confirm your password.');
    }
}
