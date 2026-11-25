<?php

namespace App\Providers;

use App\Events\AccountSecurityChanged;
use App\Notifications\AccountSecurityNotification;
use App\Support\Auth\SecurityEvents;
use App\Support\FrontendClients;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        FrontendClients::configureOrigins();

        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $valid): bool => $valid && config('starter-kit.authentication') !== 'stateful'
        );
        foreach (config('starter-kit.rate_limits') as $name => $attempts) {
            RateLimiter::for('starter-'.$name, function (Request $request) use ($name, $attempts) {
                $limits = [Limit::perMinute(max(1, $attempts))->by('ip:'.$request->ip())];
                if ($name === 'authentication') {
                    $key = $request->input('challenge_token') ?: $request->input('email', '');
                    if (is_string($key) && $key !== '') {
                        $limits[] = Limit::perMinute(max(1, $attempts))->by('credential:'.hash('sha256', strtolower($key)));
                    }
                }

                return $limits;
            });
        }

        Event::listen(AccountSecurityChanged::class, function (AccountSecurityChanged $event): void {
            Log::info('account.security.changed', ['user_id' => $event->user->getKey(), 'action' => $event->action]);
            if (config('starter-kit.security_notifications')) {
                try {
                    Notification::route('mail', $event->recipient ?? $event->user->email)
                        ->notify((new AccountSecurityNotification($event->action))->locale(app()->getLocale()));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        });

        Event::listen(PasswordReset::class, function ($event): void {
            SecurityEvents::dispatch($event->user, 'Password reset');
        });

        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return FrontendClients::url(request(), 'password_reset_path', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function ($user): string {
            $signed = URL::temporarySignedRoute(
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
