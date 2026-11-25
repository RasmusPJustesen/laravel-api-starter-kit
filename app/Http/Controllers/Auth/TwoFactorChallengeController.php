<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\LoginChallenge;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorChallengeController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        return LoginChallenge::consume($request, 'stateless', function (User $user, object $challenge) use ($request) {
            return response()->json(['token' => \App\Support\Auth\DeviceTokens::issue($user, $request, $challenge->device_name)->plainTextToken]);
        });
    }

    public function session(Request $request)
    {
        return LoginChallenge::consume($request, 'stateful', function (User $user, object $challenge) use ($request) {
            \App\Support\Auth\BrowserDevices::login($request, $user, (bool) $challenge->remember, $challenge->device_name);

            return response()->noContent();
        });
    }
}
