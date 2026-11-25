<?php

namespace App\Http\Middleware;

use App\Support\ApiErrors;
use App\Support\Auth\TwoFactor;
use Closure;
use Illuminate\Http\Request;

class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        if (! config('starter-kit.require_two_factor') || TwoFactor::enabled($request->user())) {
            return $next($request);
        }

        if ($request->is('me', 'logout', 'user/confirm-password', 'user/confirmed-password',
            'user/two-factor-authentication', 'user/two-factor-secret-key',
            'user/two-factor-qr-code', 'user/confirmed-two-factor-authentication',
            'email/verification-notification') && ! ($request->isMethod('DELETE') && $request->is('user/two-factor-authentication'))) {
            return $next($request);
        }

        return ApiErrors::response(403, 'TWO_FACTOR_ENROLLMENT_REQUIRED', __('Set up two-factor authentication to continue.'));
    }
}
