<?php

use App\Http\Controllers\Auth\SpaAuthController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

if (in_array(config('starter-kit.authentication'), ['stateful', 'both'], true)) {
    Route::prefix('spa')->middleware('throttle:5,1')->group(function (): void {
        Route::post('login', [SpaAuthController::class, 'login'])->name('spa.login');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'session'])->name('spa.two-factor.challenge');
        if (config('starter-kit.registration')) {
            Route::post('register', [SpaAuthController::class, 'register'])->name('spa.register');
        }
    });
}
