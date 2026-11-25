<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_request_does_not_disclose_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/forgot-password', ['email' => 'unknown@example.com'])->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('device');
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'New-Password-123!', 'password_confirmation' => 'New-Password-123!'];

        $this->postJson('/reset-password', $data)->assertOk();

        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertCount(0, $user->tokens);
        $this->postJson('/reset-password', $data)->assertUnprocessable();
    }
}
