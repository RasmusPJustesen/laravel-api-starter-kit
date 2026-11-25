<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ResolveLocale
{
    public function handle(Request $request, Closure $next)
    {
        $previous = App::currentLocale();
        App::setLocale($request->getPreferredLanguage(config('starter-kit.locales', ['en'])) ?: config('app.locale'));
        try {
            return $next($request);
        } finally {
            App::setLocale($previous);
        }
    }
}
