<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeviceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_two_factor_requires_a_challenge_and_consumes_recovery_code(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['single-use-code'])),
        ])->save();

        $challenge = $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(202)->json('challenge_token');
        $this->assertCount(0, $user->tokens);
        $this->postJson('/two-factor-challenge', ['challenge_token' => $challenge, 'recovery_code' => 'single-use-code'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/two-factor-challenge', ['challenge_token' => $challenge, 'recovery_code' => 'single-use-code'])->assertUnprocessable();
    }

    public function test_user_can_list_tokens_without_exposing_credentials(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Phone');
        $other = User::factory()->create()->createToken('Private device');
        $this->withToken($token->plainTextToken)->getJson('/user/sessions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.attributes.is_current', true)->assertJsonMissing(['name' => 'Private device']);
    }
}
