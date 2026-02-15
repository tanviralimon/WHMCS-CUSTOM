<?php

use App\Http\Controllers\Auth\WhmcsSsoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('client.dashboard');
});

// SSO Login Routes (public — no auth required)
Route::get('/sso/login', [WhmcsSsoController::class, 'redirect'])->name('sso.login');
Route::get('/sso/callback', [WhmcsSsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso/auto-login', [WhmcsSsoController::class, 'autoLogin'])->name('sso.auto-login');

// Old routes redirect to new client.* routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('client.dashboard'))->name('dashboard');
});

require __DIR__.'/auth.php';
