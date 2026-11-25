<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        $middleware->api(append: [\App\Http\Middleware\ResolveLocale::class]);
        $middleware->alias(['password.confirmed' => \App\Http\Middleware\RequirePasswordConfirmation::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(fn (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception) => \App\Support\ApiErrors::render($exception, $response));
    })
    ->prefersJsonResponses()
    ->create();
