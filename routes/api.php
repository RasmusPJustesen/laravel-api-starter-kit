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
        Route::post('two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'token'])->name('two-factor.challenge');
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
    Route::post('user/confirm-password', [\App\Http\Controllers\Auth\PasswordConfirmationController::class, 'store'])->middleware('throttle:5,1');
    Route::get('user/confirmed-password', [\App\Http\Controllers\Auth\PasswordConfirmationController::class, 'show']);

    if (config('starter-kit.two_factor')) {
        Route::get('user/two-factor-authentication', [\App\Http\Controllers\Auth\TwoFactorController::class, 'show']);
        Route::middleware(['password.confirmed', 'throttle:5,1'])->group(function (): void {
            Route::post('user/two-factor-authentication', [\App\Http\Controllers\Auth\TwoFactorController::class, 'store']);
            Route::delete('user/two-factor-authentication', [\App\Http\Controllers\Auth\TwoFactorController::class, 'destroy']);
            Route::get('user/two-factor-secret-key', [\App\Http\Controllers\Auth\TwoFactorController::class, 'setup']);
            Route::post('user/confirmed-two-factor-authentication', [\App\Http\Controllers\Auth\TwoFactorController::class, 'confirm']);
            Route::get('user/two-factor-recovery-codes', [\App\Http\Controllers\Auth\TwoFactorController::class, 'recoveryCodes']);
            Route::post('user/two-factor-recovery-codes', [\App\Http\Controllers\Auth\TwoFactorController::class, 'regenerate']);
        });
    }
    Route::post('token/refresh', RefreshTokenController::class)->name('token.refresh');

    Route::get('user/sessions', [\App\Http\Controllers\Auth\SessionController::class, 'index']);
    Route::middleware('password.confirmed')->group(function (): void {
        Route::delete('user/sessions/others', [\App\Http\Controllers\Auth\SessionController::class, 'others']);
        Route::delete('user/sessions/{session}', [\App\Http\Controllers\Auth\SessionController::class, 'destroy']);
        Route::patch('user/sessions/{session}', [\App\Http\Controllers\Auth\SessionController::class, 'update']);
    });

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
