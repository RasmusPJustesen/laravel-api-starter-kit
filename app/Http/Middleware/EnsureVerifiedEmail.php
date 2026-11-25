<?php

namespace App\Http\Middleware;

use App\Support\ApiErrors;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): ?Response
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
