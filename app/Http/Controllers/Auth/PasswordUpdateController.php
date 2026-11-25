<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Http\Responses\MessageResponse;
use App\Models\User;
use App\Support\Auth\Credentials;
use App\Support\Auth\SecurityEvents;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

#[Group('Password')]
class PasswordUpdateController extends Controller
{
    #[Endpoint(title: 'Update Password', description: "Update the authenticated user's password.")]
    public function __invoke(PasswordUpdateRequest $request): MessageResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
            if (! Hash::check($request->validated('current_password'), $user->getAuthPassword())) {
                throw ValidationException::withMessages(['current_password' => [__('auth.password')]]);
            }

            $user->update([
                'password' => $request->validated('password'),
            ]);

            Credentials::revokeAll($user);
            Password::deleteToken($user);
            SecurityEvents::dispatch($user, 'Password changed');
        });

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new MessageResponse('Password updated successfully.');
    }
}
