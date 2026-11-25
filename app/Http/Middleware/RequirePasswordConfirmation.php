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
            return response()->json(['errors' => [['status' => '403', 'code' => 'PASSWORD_CONFIRMATION_REQUIRED', 'detail' => __('Please confirm your password.')]]], 403);
        }

        return $next($request);
    }
}
