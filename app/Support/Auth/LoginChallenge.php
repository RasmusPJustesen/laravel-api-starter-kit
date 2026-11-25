<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginChallenge
{
    public static function issue(User $user, Request $request, string $mode): array
    {
        $plain = Str::random(64);
        DB::table('authentication_challenges')->insert([
            'id' => hash('sha256', $plain),
            'user_id' => $user->getKey(),
            'mode' => $mode,
            'credential_hash' => TwoFactor::fingerprint($user),
            'device_name' => (string) $request->string('device_name', 'auth'),
            'remember' => $request->boolean('remember'),
            'expires_at' => now()->addSeconds(config('starter-kit.challenge_lifetime', 300)),
        ]);

        return ['two_factor_required' => true, 'challenge_token' => $plain];
    }

    public static function consume(Request $request, string $mode, callable $authenticate): mixed
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'regex:/^\d{6}$/D', 'prohibits:recovery_code'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:100', 'prohibits:code'],
        ]);

        return DB::transaction(function () use ($data, $mode, $authenticate) {
            $id = hash('sha256', $data['challenge_token']);
            $challenge = DB::table('authentication_challenges')->where('id', $id)->lockForUpdate()->first();
            $user = $challenge ? User::query()->lockForUpdate()->find($challenge->user_id) : null;

            if (! $challenge || ! $user || $challenge->mode !== $mode ||
                now()->gte($challenge->expires_at) ||
                ! hash_equals($challenge->credential_hash, TwoFactor::fingerprint($user)) ||
                ! TwoFactor::enabled($user) ||
                ! TwoFactor::verify($user, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
                throw ValidationException::withMessages(['code' => [__('The authentication challenge is invalid or expired.')]]);
            }

            DB::table('authentication_challenges')->where('id', $id)->delete();

            return $authenticate($user, $challenge);
        });
    }
}
