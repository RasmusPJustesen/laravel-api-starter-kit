<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['enabled' => TwoFactor::enabled($request->user()), 'pending' => $request->user()->two_factor_secret !== null && ! TwoFactor::enabled($request->user())]);
    }

    public function store(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        DB::transaction(function () use ($request, $provider): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_if(TwoFactor::enabled($user), 409);
            $user->forceFill([
                'two_factor_secret' => Crypt::encryptString($provider->generateSecretKey(32)),
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode(TwoFactor::generateRecoveryCodes(), JSON_THROW_ON_ERROR)),
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
            ])->save();
        });

        return response()->noContent();
    }

    public function setup(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $user = $request->user();
        abort_unless($user->two_factor_secret && ! TwoFactor::enabled($user), 409);
        $secret = Crypt::decryptString($user->two_factor_secret);

        return response()->json([
            'secret' => $secret,
            'url' => $provider->qrCodeUrl(config('app.name'), $user->email, $secret),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/D']]);

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_unless($user->two_factor_secret && ! TwoFactor::enabled($user), 409);

            if (! TwoFactor::verify($user, $data['code'])) {
                throw ValidationException::withMessages(['code' => [__('Invalid authentication code.')]]);
            }

            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
            $this->revokeOthers($request, $user);
        });

        return response()->noContent();
    }

    public function recoveryCodes(Request $request)
    {
        $user = $request->user();
        abort_unless(TwoFactor::enabled($user), 409);

        return response()->json(['codes' => json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true, 512, JSON_THROW_ON_ERROR)])->header('Cache-Control', 'no-store');
    }

    public function regenerate(Request $request)
    {
        $codes = DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            $this->requireFactor($request, $user);
            $codes = TwoFactor::generateRecoveryCodes();
            $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR))])->save();

            return $codes;
        });

        return response()->json(['codes' => $codes])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request)
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());

            if (TwoFactor::enabled($user)) {
                $this->requireFactor($request, $user);
            }

            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
            ])->save();
            $this->revokeOthers($request, $user);
        });

        return response()->noContent();
    }

    private function requireFactor(Request $request, User $user): void
    {
        $data = $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'regex:/^\d{6}$/D'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:100'],
        ]);

        if (! TwoFactor::enabled($user) || ! TwoFactor::verify($user, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            throw ValidationException::withMessages(['code' => [__('Invalid authentication code.')]]);
        }
    }

    private function revokeOthers(Request $request, User $user): void
    {
        \App\Support\Auth\Credentials::revokeOthers($request, $user);
    }
}
