<?php

use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Short URL: GET /{slug} -> 302 to the destination, click logged.
// Registered last so real routes (/up, /api/*) always win.
Route::get('/{slug}', [LinkController::class, 'show'])
    ->where('slug', \App\Models\Link::SLUG_PATTERN);
