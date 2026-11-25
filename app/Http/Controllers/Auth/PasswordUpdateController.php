<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Http\Responses\MessageResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Facades\DB;

#[Group('Password')]
class PasswordUpdateController extends Controller
{
    #[Endpoint(title: 'Update Password', description: "Update the authenticated user's password.")]
    public function __invoke(PasswordUpdateRequest $request): MessageResponse
    {
        DB::transaction(function () use ($request): void {
            $user = \App\Models\User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if (! \Illuminate\Support\Facades\Hash::check($request->validated('current_password'), $user->getAuthPassword())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => [__('auth.password')]]);
            }

            $user->update([
                'password' => $request->validated('password'),
            ]);

            \App\Support\Auth\Credentials::revokeAll($user);
            \Illuminate\Support\Facades\Password::deleteToken($user);
            \App\Support\Auth\SecurityEvents::dispatch($user, 'Password changed');
        });

        if ($request->hasSession()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new MessageResponse('Password updated successfully.');
    }
}
