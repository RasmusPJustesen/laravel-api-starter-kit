<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactor
{
    public static function enabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public static function fingerprint(User $user): string
    {
        return hash('sha256', $user->getAuthPassword().'|'.$user->two_factor_secret.'|'.$user->two_factor_confirmed_at);
    }

    /** @return list<string> */
    public static function generateRecoveryCodes(): array
    {
        return array_map(fn () => Str::random(10).'-'.Str::random(10), range(1, 8));
    }

    // Call only while holding the user's row lock, in a transaction.
    public static function verify(User $user, ?string $code, ?string $recovery = null): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if ($recovery !== null && $user->two_factor_recovery_codes) {
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true, 512, JSON_THROW_ON_ERROR);
            foreach ($codes as $index => $candidate) {
                if (hash_equals($candidate, $recovery)) {
                    unset($codes[$index]);
                    $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR))])->save();

                    return true;
                }
            }

            return false;
        }

        if ($code === null || ! preg_match('/^\d{6}$/D', $code)) {
            return false;
        }

        $engine = app(Google2FA::class);
        $step = $engine->verifyKeyNewer(Crypt::decryptString($user->two_factor_secret), $code, $user->two_factor_last_step ?? 0, 1);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step === true ? $engine->getTimestamp() : $step])->save();

        return true;
    }
}
