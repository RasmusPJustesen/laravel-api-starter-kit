<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\FrontendClients;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

final class DeviceTokens
{
    /** @param list<string> $abilities */
    public static function issue(User $user, Request $request, string $name = 'auth', array $abilities = ['*'], ?string $deviceId = null): NewAccessToken
    {
        $token = $user->createToken($name, $abilities);
        $token->accessToken->forceFill([
            'device_id' => $deviceId ?? (string) Str::uuid(),
            'client_id' => FrontendClients::resolve($request)['id'],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ])->save();

        if ($deviceId === null && config('starter-kit.new_device_notifications')) {
            SecurityEvents::dispatch($user, 'New device login');
        }

        return $token;
    }
}
