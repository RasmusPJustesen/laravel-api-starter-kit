<?php

namespace App\Http\Middleware;

use App\Support\ApiErrors;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureVerifiedEmail
{
    public function handle(\Illuminate\Http\Request $request, Closure $next, ?string $redirectToRoute = null): \Symfony\Component\HttpFoundation\Response|null
    {
        if (! config('starter-kit.email_verification')) {
            return $next($request);
        }

        if (! $request->user()?->hasVerifiedEmail() && $request->expectsJson()) {
            return ApiErrors::response(403, 'EMAIL_VERIFICATION_REQUIRED', __('Your email address is not verified.'));
        }

        return app(EnsureEmailIsVerified::class)->handle($request, $next, $redirectToRoute);
    }
}
