<?php

namespace App\Http\Middleware;

use App\Support\ApiErrors;
use App\Support\Auth\PasswordConfirmation;
use Closure;
use Illuminate\Http\Request;

class RequirePasswordConfirmation
{
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        if (! PasswordConfirmation::confirmed($request)) {
            return ApiErrors::response(403, 'PASSWORD_CONFIRMATION_REQUIRED', __('Please confirm your password.'));
        }

        return $next($request);
    }
}
