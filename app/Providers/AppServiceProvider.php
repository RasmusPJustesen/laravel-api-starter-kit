<?php

namespace App\Providers;

use App\Support\FrontendClients;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        \Laravel\Fortify\Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        FrontendClients::configureOrigins();

        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $valid): bool => $valid && config('starter-kit.authentication') !== 'stateful'
        );
        foreach (config('starter-kit.rate_limits') as $name => $attempts) {
            \Illuminate\Support\Facades\RateLimiter::for('starter-'.$name, function (\Illuminate\Http\Request $request) use ($name, $attempts) {
                $limits = [\Illuminate\Cache\RateLimiting\Limit::perMinute(max(1, $attempts))->by('ip:'.$request->ip())];
                if ($name === 'authentication') {
                    $key = $request->input('challenge_token') ?: $request->input('email', '');
                    if (is_string($key) && $key !== '') {
                        $limits[] = \Illuminate\Cache\RateLimiting\Limit::perMinute(max(1, $attempts))->by('credential:'.hash('sha256', strtolower($key)));
                    }
                }

                return $limits;
            });
        }

        \Illuminate\Support\Facades\Event::listen(\App\Events\AccountSecurityChanged::class, function (\App\Events\AccountSecurityChanged $event): void {
            \Illuminate\Support\Facades\Log::info('account.security.changed', ['user_id' => $event->user->getKey(), 'action' => $event->action]);
            if (config('starter-kit.security_notifications')) {
                try {
                    \Illuminate\Support\Facades\Notification::route('mail', $event->recipient ?? $event->user->email)
                        ->notify(new \App\Notifications\AccountSecurityNotification($event->action));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\PasswordReset::class, function ($event): void {
            \App\Support\Auth\SecurityEvents::dispatch($event->user, 'Password reset');
        });

        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return FrontendClients::url(request(), 'password_reset_path', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        \Illuminate\Auth\Notifications\VerifyEmail::createUrlUsing(function ($user): string {
            $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            );
            $client = FrontendClients::resolve(request());

            return empty($client['email_verification_path'])
                ? $signed
                : FrontendClients::link($client, 'email_verification_path', ['verification_url' => $signed]);
        });

        DevCommands::except('vite');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
