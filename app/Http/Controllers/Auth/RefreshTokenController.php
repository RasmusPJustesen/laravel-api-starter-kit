<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\DeviceTokens;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

#[Group('Authentication')]
class RefreshTokenController extends Controller
{
    #[Endpoint(title: 'Refresh Token', description: 'Revoke the current API token and issue a new one.')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $3 */
        $current = $user->currentAccessToken();
        abort_unless($current instanceof PersonalAccessToken, 403);
        $token = DB::transaction(function () use ($request, $user, $current): string {
            User::query()->lockForUpdate()->whereKey($user->getKey())->firstOrFail();
            $locked = $user->tokens()->lockForUpdate()->whereKey($current->getKey())->first();
            abort_unless($locked !== null, 401);
            $name = $locked->name;
            $abilities = $locked->abilities ?? ['*'];
            $deviceId = $locked->device_id;
            $locked->delete();

            return DeviceTokens::issue($user, $request, $name, $abilities, $deviceId)->plainTextToken;
        });

        return response()->json(['token' => $token]);
    }
}
