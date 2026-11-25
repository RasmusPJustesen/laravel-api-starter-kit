<?php

use App\Support\Auth\BrowserDevices;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(fn () => DB::table('authentication_challenges')->where('expires_at', '<=', now())->delete())->hourly();

Schedule::call(fn () => BrowserDevices::table()->where('last_active_at', '<=', now()->subMinutes(config('session.lifetime')))->where(fn ($query) => $query->whereNull('remember_until')->orWhere('remember_until', '<=', now()))->delete())->daily();
