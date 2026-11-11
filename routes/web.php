<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EarnController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/earn');

// Demo sign-in. Replace with your own authentication.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// The Earn page: signed offerwall link for the signed-in user.
Route::middleware('auth')->group(function () {
    Route::get('/earn', [EarnController::class, 'show'])->name('earn');
    Route::post('/earn/age', [EarnController::class, 'confirmAge'])->name('earn.age');
});

// The postback route lives in routes/moonetix.php, outside the web group.
