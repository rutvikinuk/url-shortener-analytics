<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

// Management API: protected by the optional API_TOKEN (see config/shortener.php).
Route::middleware('api.token')->group(function () {
    Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:links-create');

    Route::middleware('throttle:api-read')->group(function () {
        Route::get('/links', [LinkController::class, 'index']);
        Route::get('/links/{slug}', [LinkController::class, 'show']);
        Route::get('/links/{slug}/analytics', [AnalyticsController::class, 'link']);
        Route::get('/analytics', [AnalyticsController::class, 'overview']);
    });
});

// The QR image is loaded by <img> (can't send headers) and only encodes the public short URL.
Route::get('/links/{slug}/qr', [LinkController::class, 'qr'])->middleware('throttle:api-read');
