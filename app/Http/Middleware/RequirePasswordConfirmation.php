<?php

namespace App\Http\Middleware;

use App\Support\Auth\PasswordConfirmation;
use Closure;
use Illuminate\Http\Request;

class RequirePasswordConfirmation
{
    public function handle(Request $request, Closure $next)
    {
        if (! PasswordConfirmation::confirmed($request)) {
            return \App\Support\ApiErrors::response(403, 'PASSWORD_CONFIRMATION_REQUIRED', __('Please confirm your password.'));
        }

        return $next($request);
    }
}
