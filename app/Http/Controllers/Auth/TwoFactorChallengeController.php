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
        return LoginChallenge::consume($request, 'stateless', function (User $user, object $challenge) {
            return response()->json(['token' => $user->createToken($challenge->device_name)->plainTextToken]);
        });
    }

    public function session(Request $request)
    {
        return LoginChallenge::consume($request, 'stateful', function (User $user, object $challenge) use ($request) {
            Auth::guard('web')->login($user, (bool) $challenge->remember);
            $request->session()->regenerate();

            return response()->noContent();
        });
    }
}
