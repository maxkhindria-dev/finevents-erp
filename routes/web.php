<?php

use App\Http\Controllers\Auth\GoogleSignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
| Three tiers of routes (a test enforces this):
|  1. Public: the sign-in page and the Google round trip.
|  2. Signed in, second factor pending: MFA setup/challenge and sign-out.
|  3. Everything else: signed in + second factor + a "can:" permission check.
*/

// 1. Public
Route::get('/', function () {
    return auth()->check() ? redirect()->route('home') : view('auth.sign-in');
})->name('login');

Route::middleware('throttle:sign-in')->group(function () {
    Route::get('/auth/google', [GoogleSignInController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleSignInController::class, 'callback'])->name('auth.google.callback');
});

// 2. Signed in, second factor pending
Route::middleware('auth')->group(function () {
    Route::get('/mfa/setup', [TwoFactorController::class, 'showSetup'])->name('mfa.setup');
    Route::post('/mfa/setup', [TwoFactorController::class, 'confirmSetup'])->middleware('throttle:mfa');
    Route::get('/mfa', [TwoFactorController::class, 'showChallenge'])->name('mfa.challenge');
    Route::post('/mfa', [TwoFactorController::class, 'verify'])->middleware('throttle:mfa');
    Route::post('/sign-out', SignOutController::class)->name('sign-out');
});

// 3. The app
Route::middleware(['auth', 'mfa'])->group(function () {
    Route::view('/home', 'home')->middleware('can:use-app')->name('home');
});
