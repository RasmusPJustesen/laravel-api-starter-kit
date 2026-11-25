<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordConfirmationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PasswordUpdateController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SendVerificationNotificationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:starter-authentication')->group(function () {
    if (in_array(config('starter-kit.authentication'), ['stateless', 'both'], true)) {
        if (config('starter-kit.registration')) {
            Route::post('register', RegisterController::class)->name('register');
        }
        Route::post('login', LoginController::class)->name('login');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'token'])->name('two-factor.challenge');
    }

    if (config('starter-kit.password_reset')) {
        Route::post('forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.reset.submit');
    }
});

if (config('starter-kit.email_verification')) {
    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');
}

Route::middleware(['auth:sanctum', 'two-factor.required'])->group(function () {
    Route::post('logout', LogoutController::class)->name('logout');
    Route::post('user/confirm-password', [PasswordConfirmationController::class, 'store'])->middleware('throttle:starter-sensitive');
    Route::get('user/confirmed-password', [PasswordConfirmationController::class, 'show']);

    if (config('starter-kit.two_factor') || config('starter-kit.require_two_factor')) {
        Route::get('user/two-factor-authentication', [TwoFactorController::class, 'show']);
        Route::middleware('password.confirmed')->group(function (): void {
            Route::post('user/two-factor-authentication', [TwoFactorController::class, 'store'])->middleware('throttle:starter-sensitive');
            Route::delete('user/two-factor-authentication', [TwoFactorController::class, 'destroy'])->middleware('throttle:starter-sensitive');
            Route::get('user/two-factor-secret-key', [TwoFactorController::class, 'setup'])->middleware('throttle:starter-sensitive-read');
            Route::get('user/two-factor-qr-code', [TwoFactorController::class, 'qrCode'])->middleware('throttle:starter-sensitive-read');
            Route::post('user/confirmed-two-factor-authentication', [TwoFactorController::class, 'confirm'])->middleware('throttle:starter-sensitive');
            Route::get('user/two-factor-recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->middleware('throttle:starter-sensitive-read');
            Route::post('user/two-factor-recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:starter-sensitive');
        });
    }
    if (in_array(config('starter-kit.authentication'), ['stateless', 'both'], true)) {
        Route::post('token/refresh', RefreshTokenController::class)->name('token.refresh');
    }

    if (config('starter-kit.device_management')) {
        Route::get('user/sessions', [SessionController::class, 'index']);
        Route::middleware('password.confirmed')->group(function (): void {
            Route::delete('user/sessions/others', [SessionController::class, 'others']);
            Route::delete('user/sessions/{session}', [SessionController::class, 'destroy']);
            Route::patch('user/sessions/{session}', [SessionController::class, 'update']);
        });

    }

    Route::get('me', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('user', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('verified')->group(function () {
        if (config('starter-kit.account_deletion')) {
            Route::delete('user', [ProfileController::class, 'destroy'])->name('profile.destroy');
        }
        Route::patch('user/password', PasswordUpdateController::class)->name('password.update');
    });

    if (config('starter-kit.email_verification')) {
        Route::post('email/verification-notification', SendVerificationNotificationController::class)
            ->middleware('throttle:starter-verification')
            ->name('verification.send');
    }
});

require __DIR__.'/readiness.php';
