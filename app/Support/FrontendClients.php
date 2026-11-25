<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class FrontendClients
{
    public static function origin(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** @return array<string, mixed> */
    public static function configured(?string $name = null): array
    {
        $clients = config('frontends.clients', []);
        $name ??= config('frontends.default');
        $client = $clients[$name] ?? null;

        if (! is_array($client) || ! self::origin($client['url'] ?? null)) {
            $name = config('frontends.default');
            $client = $clients[$name] ?? null;
        }

        abort_unless(is_array($client) && self::origin($client['url'] ?? null), 503, __('No frontend is configured.'));

        return ['id' => $name] + $client;
    }

    /** @return array<string, mixed> */
    public static function resolve(Request $request): array
    {
        return self::configured($request->header('X-Client'));
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $parameters
     */
    public static function link(array $client, string $pathKey, array $parameters = []): string
    {
        $path = $client[$pathKey] ?? null;
        abort_unless(is_string($path) && $path !== '' && ! str_contains($path, '?') && ! str_contains($path, '#'), 503, __('No frontend path is configured.'));

        return rtrim($client['url'], '/').'/'.ltrim($path, '/').($parameters ? '?'.Arr::query($parameters) : '');
    }

    /** @param array<string, mixed> $parameters */
    public static function url(Request $request, string $pathKey, array $parameters = []): string
    {
        return self::link(self::resolve($request), $pathKey, $parameters);
    }

    public static function configureOrigins(): void
    {
        $origins = [];
        $stateful = [];

        foreach (config('frontends.clients', []) as $client) {
            $origin = self::origin($client['origin'] ?? null);
            if ($origin === null) {
                continue;
            }

            $origins[] = $origin;
            if (in_array($client['authentication'] ?? '', ['stateful', 'both'], true)) {
                $stateful[] = substr($origin, strpos($origin, '://') + 3);
            }
        }

        if (config('cors.allowed_origins') === null) {
            config(['cors.allowed_origins' => array_values(array_unique($origins))]);
        }

        if (config('starter-kit.authentication') === 'stateless') {
            config(['sanctum.stateful' => [], 'sanctum.guard' => []]);
        } elseif (config('sanctum.stateful') === null) {
            config(['sanctum.stateful' => array_values(array_unique($stateful))]);
        }
    }
}
