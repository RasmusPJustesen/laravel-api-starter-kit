<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Responses\MessageResponse;
use App\Models\User;
use App\Support\Auth\Credentials;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function store(ForgotPasswordRequest $request): MessageResponse
    {
        Password::sendResetLink($request->validated());

        return new MessageResponse('If an account exists, a password reset link will be sent.');
    }

    public function update(ResetPasswordRequest $request): MessageResponse
    {
        $lock = Cache::lock('password-reset:'.hash('sha256', strtolower($request->validated('email'))), 30);
        try {
            $status = $lock->block(5, fn () => Password::reset($request->validated(), function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user = User::query()->lockForUpdate()->whereKey($user->getKey())->firstOrFail();
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();
                    Credentials::revokeAll($user);
                });

                event(new PasswordReset($user));
            }));
        } catch (LockTimeoutException) {
            abort(429, __('Please try again.'));
        }

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__('The password reset token is invalid or expired.')]]);
        }

        return new MessageResponse('Password reset successfully.');
    }
}
