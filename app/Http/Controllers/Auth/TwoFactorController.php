<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\Credentials;
use App\Support\Auth\SecurityEvents;
use App\Support\Auth\TwoFactor;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['enabled' => TwoFactor::enabled($request->user()), 'pending' => $request->user()->two_factor_secret !== null && ! TwoFactor::enabled($request->user())]);
    }

    public function store(Request $request): Response
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
            abort_if(TwoFactor::enabled($user), 409);
            $user->forceFill([
                'two_factor_secret' => Crypt::encryptString(app(Google2FA::class)->generateSecretKey(32)),
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode(TwoFactor::generateRecoveryCodes(), JSON_THROW_ON_ERROR)),
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
            ])->save();
        });

        return response()->noContent();
    }

    public function setup(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->two_factor_secret && ! TwoFactor::enabled($user), 409);
        $secret = Crypt::decryptString($user->two_factor_secret);

        return response()->json([
            'secret' => $secret,
            'url' => $provider->qrCodeUrl(config('app.name'), $user->email, $secret),
        ])->header('Cache-Control', 'no-store');
    }

    public function qrCode(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->two_factor_secret && ! TwoFactor::enabled($user), 409);
        $url = $provider->qrCodeUrl(config('app.name'), $user->email, Crypt::decryptString($user->two_factor_secret));

        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd
        )))->writeString($url);

        return response()->json(['svg' => $svg, 'url' => $url])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request): Response
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/D']]);

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
            abort_unless($user->two_factor_secret && ! TwoFactor::enabled($user), 409);

            if (! TwoFactor::verify($user, $data['code'])) {
                throw ValidationException::withMessages(['code' => [__('Invalid authentication code.')]]);
            }

            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
            SecurityEvents::dispatch($user, 'Two-factor authentication enabled');
            $this->revokeOthers($request, $user);
        });

        return response()->noContent();
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(TwoFactor::enabled($user), 409);

        return response()->json(['codes' => json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true, 512, JSON_THROW_ON_ERROR)])->header('Cache-Control', 'no-store');
    }

    public function regenerate(Request $request): JsonResponse
    {
        $codes = DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
            $this->requireFactor($request, $user);
            $codes = TwoFactor::generateRecoveryCodes();
            $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR))])->save();

            SecurityEvents::dispatch($user, 'Recovery codes regenerated');

            return $codes;
        });

        return response()->json(['codes' => $codes])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): Response
    {
        abort_if(config('starter-kit.require_two_factor'), 403, __('Two-factor authentication is required.'));
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();

            if (TwoFactor::enabled($user)) {
                $this->requireFactor($request, $user);
            }

            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
            ])->save();
            SecurityEvents::dispatch($user, 'Two-factor authentication disabled');
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
        Credentials::revokeOthers($request, $user);
    }
}
