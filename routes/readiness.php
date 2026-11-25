<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/ready', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ready']);
    } catch (\Throwable) {
        return \App\Support\ApiErrors::response(503, 'SERVER_ERROR', __('Database unavailable.'));
    }
})->middleware('throttle:60,1');
