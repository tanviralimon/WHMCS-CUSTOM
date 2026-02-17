<?php

use App\Http\Controllers\Auth\WhmcsSsoController;
use App\Http\Controllers\Client\PaymentController;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('client.dashboard');
});

// Temporary debug route to inspect WHMCS product API response
Route::get('/debug/whmcs-products', function () {
    $whmcs = app(WhmcsService::class);
    $result = $whmcs->getProducts();
    $products = $result['products']['product'] ?? [];
    $output = [];
    foreach (array_slice($products, 0, 3) as $p) {
        $output[] = [
            'all_keys' => array_keys($p),
            'pid' => $p['pid'] ?? null,
            'gid' => $p['gid'] ?? null,
            'name' => $p['name'] ?? null,
            'groupname' => $p['groupname'] ?? '*** NOT FOUND ***',
            'group_name' => $p['group_name'] ?? '*** NOT FOUND ***',
            'productgroupname' => $p['productgroupname'] ?? '*** NOT FOUND ***',
            'product_group_name' => $p['product_group_name'] ?? '*** NOT FOUND ***',
            'hidden' => $p['hidden'] ?? null,
        ];
    }
    return response()->json($output, 200, [], JSON_PRETTY_PRINT);
})->middleware('auth');

// SSO Login Routes (public — no auth required)
Route::get('/sso/login', [WhmcsSsoController::class, 'redirect'])->name('sso.login');
Route::get('/sso/callback', [WhmcsSsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso/auto-login', [WhmcsSsoController::class, 'autoLogin'])->name('sso.auto-login');

// ─── Payment Gateway Callbacks (public, no auth/CSRF/session) ──────
// SSLCommerz redirects user's browser via cross-site POST. With SameSite=Lax,
// session cookies aren't sent on cross-site POST, so StartSession would create
// a new session and overwrite the old one — logging the user out.
// Solution: exclude session middleware entirely; use query params for status.
Route::match(['get', 'post'], '/client/payment/{id}/callback/{gateway}', [PaymentController::class, 'callback'])
    ->name('client.payment.callback')
    ->where('gateway', '[a-z]+')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);

// ─── Payment Proof Download (signed URL, no login required) ────────
Route::get('/payment-proof/{invoice}/{file}', [PaymentController::class, 'downloadPaymentProof'])
    ->name('payment-proof.download')
    ->middleware('signed');

// Old routes redirect to new client.* routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('client.dashboard'))->name('dashboard');
});

require __DIR__.'/auth.php';
