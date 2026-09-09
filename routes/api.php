<?php

use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::get('/links', [LinkController::class, 'index']);
Route::post('/links', [LinkController::class, 'store']);
Route::get('/links/{slug}', [LinkController::class, 'show']);
