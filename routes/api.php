<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:links-create');

Route::middleware('throttle:api-read')->group(function () {
    Route::get('/links', [LinkController::class, 'index']);
    Route::get('/links/{slug}', [LinkController::class, 'show']);
    Route::get('/links/{slug}/qr', [LinkController::class, 'qr']);
    Route::get('/links/{slug}/analytics', [AnalyticsController::class, 'link']);
    Route::get('/analytics', [AnalyticsController::class, 'overview']);
});
