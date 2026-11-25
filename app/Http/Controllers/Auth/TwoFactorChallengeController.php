<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\BrowserDevices;
use App\Support\Auth\DeviceTokens;
use App\Support\Auth\LoginChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwoFactorChallengeController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        return LoginChallenge::consume($request, 'stateless', function (User $user, \stdClass $challenge) use ($request) {
            return response()->json(['token' => DeviceTokens::issue($user, $request, $challenge->device_name)->plainTextToken]);
        });
    }

    public function session(Request $request): Response
    {
        return LoginChallenge::consume($request, 'stateful', function (User $user, \stdClass $challenge) use ($request) {
            BrowserDevices::login($request, $user, (bool) $challenge->remember, $challenge->device_name);

            return response()->noContent();
        });
    }
}
