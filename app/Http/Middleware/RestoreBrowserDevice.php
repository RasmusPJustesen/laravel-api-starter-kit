<?php

namespace App\Http\Middleware;

use App\Support\Auth\BrowserDevices;
use Closure;
use Illuminate\Http\Request;

class RestoreBrowserDevice
{
    public function handle(Request $request, Closure $next)
    {
        BrowserDevices::restore($request);

        return $next($request);
    }
}
