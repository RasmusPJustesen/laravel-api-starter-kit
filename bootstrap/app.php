<?php

use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\RequirePasswordConfirmation;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\ResolveLocale;
use App\Http\Middleware\RestoreBrowserDevice;
use App\Support\ApiErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/spa.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
        // Middleware configuration runs before the config repository is loaded.
        // Sanctum's configured stateful domains decide whether to start a session.
        $middleware->statefulApi();
        $middleware->api(append: [RestoreBrowserDevice::class]);
        $middleware->prepend(ResolveLocale::class);
        $middleware->appendToPriorityList(StartSession::class, RestoreBrowserDevice::class);
        $middleware->web(append: [RestoreBrowserDevice::class]);
        $middleware->alias([
            'password.confirmed' => RequirePasswordConfirmation::class,
            'verified' => EnsureVerifiedEmail::class,
            'two-factor.required' => RequireTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(fn (Response $response, Throwable $exception) => ApiErrors::render($exception, $response));
    })
    ->prefersJsonResponses()
    ->create();
