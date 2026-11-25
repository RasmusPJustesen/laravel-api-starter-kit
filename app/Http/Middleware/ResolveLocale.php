<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ResolveLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = App::currentLocale();
        App::setLocale($request->getPreferredLanguage(config('starter-kit.locales', ['en'])) ?: config('app.locale'));
        try {
            $response = $next($request);
            $response->headers->set('Content-Language', App::currentLocale());
            $response->setVary('Accept-Language', false);

            return $response;
        } finally {
            App::setLocale($previous);
        }
    }
}
