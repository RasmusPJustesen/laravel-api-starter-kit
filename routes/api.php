<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordUpdateController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SendVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SpaAuthController;

Route::middleware('throttle:5,1')->group(function () {
    if (in_array(config('starter-kit.authentication'), ['stateless', 'both'], true)) {
        if (config('starter-kit.registration')) {
            Route::post('register', RegisterController::class)->name('register');
        }
        Route::post('login', LoginController::class)->name('login');
    }

    if (in_array(config('starter-kit.authentication'), ['stateful', 'both'], true)) {
        Route::middleware('web')->prefix('spa')->group(function () {
            Route::post('login', [SpaAuthController::class, 'login'])->name('spa.login');
            if (config('starter-kit.registration')) {
                Route::post('register', [SpaAuthController::class, 'register'])->name('spa.register');
            }
        });
    }
    if (config('starter-kit.password_reset')) {
        Route::post('forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.reset.submit');
    }
});

Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', LogoutController::class)->name('logout');
    Route::post('token/refresh', RefreshTokenController::class)->name('token.refresh');

    Route::get('me', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('user', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('verified')->group(function () {
        if (config('starter-kit.account_deletion')) {
            Route::delete('user', [ProfileController::class, 'destroy'])->name('profile.destroy');
        }
        Route::patch('user/password', PasswordUpdateController::class)->name('password.update');
    });

    Route::post('email/verification-notification', SendVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
