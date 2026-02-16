<?php

use App\Http\Controllers\Auth\WhmcsSsoController;
use App\Http\Controllers\Client\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('client.dashboard');
});

// SSO Login Routes (public — no auth required)
Route::get('/sso/login', [WhmcsSsoController::class, 'redirect'])->name('sso.login');
Route::get('/sso/callback', [WhmcsSsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso/auto-login', [WhmcsSsoController::class, 'autoLogin'])->name('sso.auto-login');

// ─── Payment Gateway Callbacks (public, no auth/CSRF) ──────
// SSLCommerz, etc. send server-to-server POST callbacks without cookies/CSRF.
// These must be outside auth middleware and CSRF protection.
Route::match(['get', 'post'], '/client/payment/{id}/callback/{gateway}', [PaymentController::class, 'callback'])
    ->name('client.payment.callback')
    ->where('gateway', '[a-z]+');

// Old routes redirect to new client.* routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('client.dashboard'))->name('dashboard');
});

require __DIR__.'/auth.php';
