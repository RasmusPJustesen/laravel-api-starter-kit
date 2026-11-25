<?php

namespace App\Providers;

use App\Support\ApiContract;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define(
            'viewApiDocs',
            fn (?Authenticatable $user = null): bool => app()->environment(['local', 'testing']),
        );

        Scramble::configure()
            ->withDocumentTransformers(fn (OpenApi $api) => ApiContract::transform($api))
            ->expose(ui: '/docs', document: '/docs/api.json');
    }
}
