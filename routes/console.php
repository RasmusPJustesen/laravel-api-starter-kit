<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();

\Illuminate\Support\Facades\Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('authentication_challenges')->where('expires_at', '<=', now())->delete())->hourly();

\Illuminate\Support\Facades\Schedule::call(fn () => \App\Support\Auth\BrowserDevices::table()->where('last_active_at', '<=', now()->subMinutes(config('session.lifetime')))->where(fn ($query) => $query->whereNull('remember_until')->orWhere('remember_until', '<=', now()))->delete())->daily();
