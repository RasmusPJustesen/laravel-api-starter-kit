<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class FrontendClients
{
    public static function resolve(Request $request): array
    {
        $clients = config('frontends.clients', []);
        $name = $request->header('X-Client', config('frontends.default'));
        $client = $clients[$name] ?? null;

        if (! is_array($client) || empty($client['url'])) {
            $name = config('frontends.default');
            $client = $clients[$name] ?? null;
        }

        abort_unless(is_array($client) && ! empty($client['url']), 503, __('No frontend is configured.'));

        return ['id' => $name] + $client;
    }

    public static function url(Request $request, string $pathKey, array $parameters = []): string
    {
        $client = self::resolve($request);
        $base = rtrim($client['url'], '/');
        $path = '/'.ltrim($client[$pathKey] ?? '', '/');

        return $base.$path.($parameters ? '?'.Arr::query($parameters) : '');
    }
}
