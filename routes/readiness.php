<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/ready', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ready']);
    } catch (\Throwable) {
        return response()->json(['status' => 'unavailable'], 503);
    }
})->middleware('throttle:60,1');
