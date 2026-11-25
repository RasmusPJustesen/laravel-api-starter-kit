<?php

namespace App\Http\Middleware;

use App\Support\Auth\BrowserDevices;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestoreBrowserDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        BrowserDevices::restore($request);

        return $next($request);
    }
}
