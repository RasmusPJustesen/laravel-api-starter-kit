<?php

namespace App\Support\Auth;

/**
 * Best-effort display metadata, never an authentication or authorization signal.
 */
final class UserAgent
{
    /** @return array{device: string|null, browser: string|null, platform: string|null} */
    public static function describe(?string $agent): array
    {
        if (! $agent) {
            return ['device' => null, 'browser' => null, 'platform' => null];
        }

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            preg_match('/iPhone|iPad|iPod/i', $agent) === 1 => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Macintosh') || str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };
        $browser = match (true) {
            preg_match('/Edg(?:A|iOS)?\//', $agent) === 1 => 'Edge',
            str_contains($agent, 'SamsungBrowser/') => 'Samsung Internet',
            preg_match('/OPR\/|Opera\//', $agent) === 1 => 'Opera',
            preg_match('/Firefox\/|FxiOS\//', $agent) === 1 => 'Firefox',
            preg_match('/Chrome\/|CriOS\//', $agent) === 1 => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };
        $device = match (true) {
            str_contains($agent, 'iPad') || ($platform === 'Android' && ! str_contains($agent, 'Mobile')) => 'Tablet',
            $platform === 'iOS' || $platform === 'Android' || str_contains($agent, 'Mobile') => 'Mobile',
            $platform !== null => 'Desktop',
            default => null,
        };

        return ['device' => $device, 'browser' => $browser, 'platform' => $platform];
    }
}
