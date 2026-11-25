<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Authentication')]
class RefreshTokenController extends Controller
{
    #[Endpoint(title: 'Refresh Token', description: 'Revoke the current API token and issue a new one.')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $current = $user->currentAccessToken();
        abort_unless($current instanceof \Laravel\Sanctum\PersonalAccessToken, 403);
        $token = DB::transaction(function () use ($request, $user, $current): string {
            $locked = $user->tokens()->lockForUpdate()->find($current->getKey());
            abort_unless($locked, 401);
            $name = $locked->name;
            $abilities = $locked->abilities ?? ['*'];
            $deviceId = $locked->device_id;
            $locked->delete();

            return \App\Support\Auth\DeviceTokens::issue($user, $request, $name, $abilities, $deviceId)->plainTextToken;
        });

        return response()->json(['token' => $token]);
    }
}
