<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FrontendNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_links_are_generated_from_registered_frontends(): void
    {
        Notification::fake();
        config([
            'frontends.clients.mobile.url' => 'https://links.example.test',
            'frontends.clients.mobile.email_verification_path' => '/verify-email',
        ]);
        $user = User::factory()->unverified()->create();
        $this->withHeader('X-Client', 'mobile')->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, fn ($notification) =>
            str_starts_with($notification->toMail($user)->actionUrl, 'https://links.example.test/reset-password?')
        );
        $this->withToken($user->createToken('device')->plainTextToken)->postJson('/email/verification-notification')->assertAccepted();
        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;
            return str_starts_with($url, 'https://links.example.test/verify-email?');
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->getJson($query['verification_url'])->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unregistered_client_cannot_control_notification_destination(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->withHeader('X-Client', 'https://untrusted.example.test')->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, fn ($notification) =>
            str_starts_with($notification->toMail($user)->actionUrl, config('frontends.clients.web.url').'/reset-password?')
        );
    }
}
