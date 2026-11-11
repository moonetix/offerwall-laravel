<?php

use App\Http\Controllers\MoonetixPostbackController;
use Illuminate\Support\Facades\Route;

// Loaded from bootstrap/app.php without the web middleware group: no session,
// no cookies, no CSRF. Moonetix calls it server to server, and the hash protects it.
// Do not put it behind auth, a redirect, or a per-IP rate limit.
Route::get('/moonetix/postback', MoonetixPostbackController::class)->name('moonetix.postback');
