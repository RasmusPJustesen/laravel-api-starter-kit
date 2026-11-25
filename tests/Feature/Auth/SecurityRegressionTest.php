<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['starter-authentication', 'starter-sensitive'] as $limiter) {
            RateLimiter::for($limiter, fn () => Limit::perMinute(100));
        }
    }

    public function test_totp_enrollment_requires_confirmation_and_rejects_replay(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = $user->createToken('setup')->plainTextToken;
        $other = $user->createToken('other');

        $this->withToken($token)->postJson('/user/two-factor-authentication')->assertForbidden();
        $this->withToken($token)->postJson('/user/confirm-password', ['password' => 'password'])->assertNoContent();
        $this->withToken($token)->postJson('/user/two-factor-authentication')->assertNoContent();
        auth()->forgetGuards();
        $setup = $this->withToken($token)->getJson('/user/two-factor-secret-key')->assertOk()->json();
        $this->assertStringStartsWith('otpauth://', $setup['url']);
        $this->assertNotSame($setup['secret'], $user->fresh()->two_factor_secret);
        $this->withToken($token)->getJson('/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        $this->withToken($token)->postJson('/user/confirmed-two-factor-authentication', ['code' => $code])->assertNoContent();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertModelMissing($other->accessToken);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/user/two-factor-secret-key')->assertConflict();
        $codes = $this->withToken($token)->getJson('/user/two-factor-recovery-codes')->assertOk()->json('codes');
        $this->assertCount(8, $codes);

        auth()->forgetGuards();
        $this->flushHeaders();
        $challenge = $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(202)->json('challenge_token');
        $this->postJson('/two-factor-challenge', ['challenge_token' => $challenge, 'code' => $code])->assertUnprocessable();
        $this->postJson('/two-factor-challenge', ['challenge_token' => $challenge, 'recovery_code' => $codes[0]])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/two-factor-challenge', ['challenge_token' => $challenge, 'recovery_code' => $codes[0]])->assertUnprocessable();
    }

    public function test_totp_login_and_challenge_mode_binding(): void
    {
        $user = User::factory()->create();
        $secret = app(Google2FA::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['backup-code'])),
        ])->save();

        $challenge = $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(202)->json('challenge_token');
        $this->postJson('/spa/two-factor-challenge', ['challenge_token' => $challenge, 'recovery_code' => 'backup-code'])->assertUnprocessable();
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->postJson('/two-factor-challenge', ['challenge_token' => $challenge, 'code' => $code])->assertOk();
        $next = $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(202)->json('challenge_token');
        $this->postJson('/two-factor-challenge', ['challenge_token' => $next, 'code' => $code])->assertUnprocessable();
        $this->travel(6)->minutes();
        $this->postJson('/two-factor-challenge', ['challenge_token' => $next, 'recovery_code' => 'backup-code'])->assertUnprocessable();
    }

    public function test_required_two_factor_restricts_non_enrolled_users(): void
    {
        config(['starter-kit.require_two_factor' => true]);
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/me')->assertOk();
        $this->withToken($token)->getJson('/user/sessions')->assertForbidden()
            ->assertJsonPath('error_details.0.code', 'TWO_FACTOR_ENROLLMENT_REQUIRED');
        $this->withToken($token)->postJson('/user/confirm-password', ['password' => 'password'])->assertNoContent();
        $this->withToken($token)->postJson('/user/two-factor-authentication')->assertNoContent();
    }

    public function test_stateful_mode_rejects_valid_bearer_tokens(): void
    {
        config(['starter-kit.authentication' => 'stateful']);
        $user = User::factory()->create();
        $this->withToken($user->createToken('device')->plainTextToken)->getJson('/me')->assertUnauthorized();
    }

    public function test_token_confirmation_is_not_shared_between_devices(): void
    {
        $user = User::factory()->create();
        $first = $user->createToken('first')->plainTextToken;
        $second = $user->createToken('second')->plainTextToken;

        $this->withToken($first)->postJson('/user/confirm-password', ['password' => 'password'])->assertNoContent();
        auth()->forgetGuards();
        $this->withToken($second)->deleteJson('/user/sessions/others')->assertForbidden();
        $this->assertCount(2, $user->tokens);
        auth()->forgetGuards();
        $this->withToken($first)->deleteJson('/user/sessions/others')->assertNoContent();
        auth()->forgetGuards();
        $this->withToken($second)->getJson('/me')->assertUnauthorized();
    }
}
