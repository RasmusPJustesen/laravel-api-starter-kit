<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DeleteUserRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use App\Support\Auth\Credentials;
use App\Support\Auth\PasswordConfirmation;
use App\Support\Auth\SecurityEvents;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Group('Profile')]
class ProfileController extends Controller
{
    #[Endpoint(title: 'Current User', description: "Get the authenticated user's information.")]
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    #[Endpoint(title: 'Update Profile', description: "Update the authenticated user's name and/or email.")]
    public function update(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $previousEmail = $user->email;
        $user->fill($request->validated());

        if (! $user->isDirty('email') || ! ($user instanceof MustVerifyEmail)) {
            $user->save();

            return new UserResource($user);
        }

        if (! PasswordConfirmation::confirmed($request)) {
            abort(403, __('Please confirm your password.'), ['X-API-Error-Code' => 'PASSWORD_CONFIRMATION_REQUIRED']);
        }

        $user->email_verified_at = null;
        $user->save();
        $user->sendEmailVerificationNotification();
        SecurityEvents::dispatch($user, 'Email changed', $previousEmail);

        return new UserResource($user);
    }

    #[Endpoint(title: 'Delete Account', description: "Permanently delete the authenticated user's account and revoke all their tokens.")]
    public function destroy(DeleteUserRequest $request): Response
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            Credentials::revokeAll($user);
            $user->delete();
        });

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
