<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

final class PasswordConfirmation
{
    private static function key(Request $request): string
    {
        $user = $request->user();
        /** @var PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $token */
        $token = $user->currentAccessToken();
        $id = $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'session:'.$request->session()->getId();

        return 'password-confirmation:'.hash_hmac('sha256', $user->getKey().'|'.$user->getAuthPassword().'|'.$id, config('app.key'));
    }

    public static function confirm(Request $request): void
    {
        Cache::put(self::key($request), true, (int) config('auth.password_timeout', 900));
    }

    public static function confirmed(Request $request): bool
    {
        return Cache::get(self::key($request), false) === true;
    }
}
