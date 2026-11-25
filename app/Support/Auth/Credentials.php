<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class Credentials
{
    public static function sessions(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }

    public static function revokeOthers(Request $request, User $user): void
    {
        /** @var PersonalAccessToken|\Laravel\Sanctum\TransientToken|null $3 */
        $current = $request->user()->currentAccessToken();
        $tokens = $user->tokens();

        if ($current instanceof PersonalAccessToken) {
            $tokens->whereKeyNot($current->getKey());
        }

        $tokens->delete();

        $devices = BrowserDevices::table()->where('user_id', $user->getKey());
        if (! ($current instanceof PersonalAccessToken) && $request->hasSession()) {
            $devices->where('id', '!=', $request->session()->get('browser_device_id', ''));
        }
        $devices->delete();
        DB::table('authentication_challenges')->where('user_id', $user->getKey())->delete();

        if (config('session.driver') === 'database') {
            $sessions = self::sessions()->where('user_id', $user->getKey());
            if (! ($current instanceof PersonalAccessToken) && $request->hasSession()) {
                $sessions->where('id', '!=', $request->session()->getId());
            }
            $sessions->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    public static function revokeAll(User $user): void
    {
        $user->tokens()->delete();
        BrowserDevices::table()->where('user_id', $user->getKey())->delete();
        if (config('session.driver') === 'database') {
            self::sessions()->where('user_id', $user->getKey())->delete();
        }
        DB::table('authentication_challenges')->where('user_id', $user->getKey())->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
