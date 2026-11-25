<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureVerifiedEmail extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (! config('starter-kit.email_verification')) {
            return $next($request);
        }

        if (! $request->user()?->hasVerifiedEmail() && $request->expectsJson()) {
            return \App\Support\ApiErrors::response(403, 'EMAIL_VERIFICATION_REQUIRED', __('Your email address is not verified.'));
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
