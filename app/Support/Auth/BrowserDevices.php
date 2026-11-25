<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\FrontendClients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BrowserDevices
{
    public const COOKIE = 'starter_device';

    public static function table(): \Illuminate\Database\Query\Builder
    {
        return DB::table('browser_devices');
    }

    public static function login(Request $request, User $user, bool $remember = false, string $name = 'Browser'): void
    {
        self::logout($request);
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $id = (string) Str::uuid();
        $remember = $remember && config('starter-kit.remember_me');
        $secret = $remember ? Str::random(64) : null;
        $minutes = max(1, (int) config('starter-kit.remember_days')) * 1440;

        self::table()->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'session_id' => $request->session()->getId(),
            'remember_hash' => $secret ? hash('sha256', $secret) : null,
            'remember_until' => $secret ? now()->addMinutes($minutes) : null,
            'name' => $name,
            'client_id' => FrontendClients::resolve($request)['id'],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'last_active_at' => now(),
        ]);
        $request->session()->put('browser_device_id', $id);
        if ($secret) {
            Cookie::queue(self::COOKIE, $id.'.'.$secret, $minutes, null, null, config('session.secure'), true, false, 'lax');
        }
        if (config('starter-kit.new_device_notifications')) {
            SecurityEvents::dispatch($user, 'New device login');
        }
    }

    public static function restore(Request $request): void
    {
        if (! $request->hasSession() || config('starter-kit.authentication') === 'stateless') {
            return;
        }

        $id = $request->session()->get('browser_device_id');
        $guard = Auth::guard('web');
        if ($id) {
            $device = self::table()->where('id', $id)->first();
            if ($device && (string) $guard->id() === (string) $device->user_id && $device->session_id === $request->session()->getId()) {
                self::table()->where('id', $id)->update(['last_active_at' => now()]);

                return;
            }
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Cookie::queue(Cookie::forget(self::COOKIE));

            return;
        }

        if ($guard->check() || ! config('starter-kit.remember_me')) {
            return;
        }

        $cookie = $request->cookie(self::COOKIE);
        if (! is_string($cookie) || ! str_contains($cookie, '.')) {
            return;
        }
        [$id, $secret] = explode('.', $cookie, 2);
        if (! Str::isUuid($id) || strlen($secret) !== 64) {
            return;
        }

        DB::transaction(function () use ($request, $id, $secret, $guard): void {
            $candidate = self::table()->where('id', $id)->first();
            $user = $candidate ? User::query()->lockForUpdate()->whereKey($candidate->user_id)->first() : null;
            $device = self::table()->where('id', $id)->lockForUpdate()->first();
            if (! $user || ! $device || ! $device->remember_hash || ! hash_equals($device->remember_hash, hash('sha256', $secret)) || now()->gte($device->remember_until)) {
                Cookie::queue(Cookie::forget(self::COOKIE));

                return;
            }
            $guard->login($user, false);
            $request->session()->regenerate();
            $request->session()->put('browser_device_id', $id);
            $next = Str::random(64);
            self::table()->where('id', $id)->update([
                'session_id' => $request->session()->getId(),
                'remember_hash' => hash('sha256', $next),
                'last_active_at' => now(),
            ]);
            Cookie::queue(self::COOKIE, $id.'.'.$next, max(1, (int) now()->diffInMinutes($device->remember_until)), null, null, config('session.secure'), true, false, 'lax');
        });
    }

    public static function logout(Request $request): void
    {
        if ($request->hasSession() && $id = $request->session()->get('browser_device_id')) {
            self::table()->where('id', $id)->where('user_id', Auth::guard('web')->id())->delete();
            $request->session()->forget('browser_device_id');
        }
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public static function active(\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Query\Builder
    {
        return $query->where(function ($query): void {
            $query->where('last_active_at', '>', now()->subMinutes(config('session.lifetime')))
                ->orWhere('remember_until', '>', now());
        });
    }
}
