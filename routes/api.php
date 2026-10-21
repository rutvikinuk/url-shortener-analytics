<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::get('/links', [LinkController::class, 'index']);
Route::post('/links', [LinkController::class, 'store']);
Route::get('/links/{slug}', [LinkController::class, 'show']);
Route::get('/links/{slug}/analytics', [AnalyticsController::class, 'link']);
Route::get('/analytics', [AnalyticsController::class, 'overview']);
