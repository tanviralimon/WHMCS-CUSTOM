<?php

use App\Http\Controllers\Client\AccountController;
use App\Http\Controllers\Client\AddonController;
use App\Http\Controllers\Client\AffiliateController;
use App\Http\Controllers\Client\AnnouncementController;
use App\Http\Controllers\Client\BillingController;
use App\Http\Controllers\Client\CurrencyController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\DomainController;
use App\Http\Controllers\Client\DownloadController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\KnowledgebaseController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\ServiceController;
use App\Http\Controllers\Client\SsoController;
use App\Http\Controllers\Client\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Portal Routes
|--------------------------------------------------------------------------
| All routes are prefixed with /client and named client.*
| Loaded via bootstrap/app.php with web + auth middleware.
*/

// ─── Dashboard ──────────────────────────────────────────────
Route::get('/', DashboardController::class)->name('dashboard');

// ─── Services ───────────────────────────────────────────────
Route::prefix('services')->name('services.')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('index');
    Route::get('/{id}', [ServiceController::class, 'show'])->name('show')
        ->middleware('whmcs.own:service,id');
    Route::post('/{id}/cancel', [ServiceController::class, 'requestCancel'])->name('cancel')
        ->middleware(['whmcs.own:service,id', 'throttle:10,1']);
    Route::post('/{id}/change-password', [ServiceController::class, 'changePassword'])->name('changePassword')
        ->middleware(['whmcs.own:service,id', 'throttle:5,1']);
    Route::get('/{id}/sso', [ServiceController::class, 'ssoLogin'])->name('sso')
        ->middleware(['whmcs.own:service,id', 'throttle:10,1']);
    Route::post('/{id}/action', [ServiceController::class, 'moduleAction'])->name('action')
        ->middleware(['whmcs.own:service,id', 'throttle:10,1']);
    Route::get('/{id}/os-templates', [ServiceController::class, 'getOsTemplates'])->name('osTemplates')
        ->middleware(['whmcs.own:service,id', 'throttle:10,1']);
    Route::post('/{id}/rebuild', [ServiceController::class, 'rebuildVps'])->name('rebuild')
        ->middleware(['whmcs.own:service,id', 'throttle:3,1']);
    Route::get('/{id}/upgrade-options', [ServiceController::class, 'upgradeOptions'])->name('upgradeOptions')
        ->middleware(['whmcs.own:service,id', 'throttle:10,1']);
    Route::post('/{id}/upgrade-calculate', [ServiceController::class, 'calculateUpgrade'])->name('upgradeCalculate')
        ->middleware(['whmcs.own:service,id', 'throttle:15,1']);
    Route::post('/{id}/upgrade', [ServiceController::class, 'submitUpgrade'])->name('upgrade')
        ->middleware(['whmcs.own:service,id', 'throttle:5,1']);
    Route::get('/{id}/config-options', [ServiceController::class, 'configOptions'])->name('configOptions')
        ->middleware(['whmcs.own:service,id', 'throttle:10,1']);
    Route::post('/{id}/config-calculate', [ServiceController::class, 'calculateConfigUpgrade'])->name('configCalculate')
        ->middleware(['whmcs.own:service,id', 'throttle:15,1']);
    Route::post('/{id}/config-upgrade', [ServiceController::class, 'submitConfigUpgrade'])->name('configUpgrade')
        ->middleware(['whmcs.own:service,id', 'throttle:5,1']);
});

// ─── Domains ────────────────────────────────────────────────
Route::middleware('feature:domains')->prefix('domains')->name('domains.')->group(function () {
    Route::get('/', [DomainController::class, 'index'])->name('index');
    Route::get('/search', [DomainController::class, 'searchDomain'])->name('search');
    Route::post('/check', [DomainController::class, 'checkAvailability'])->name('check');
    Route::get('/pricing', [DomainController::class, 'pricing'])->name('pricing');
    Route::post('/cart/add', [DomainController::class, 'addToCart'])->name('cart.add');
    Route::get('/{id}', [DomainController::class, 'show'])->name('show')
        ->middleware('whmcs.own:domain,id');
    Route::post('/{id}/renew', [DomainController::class, 'renew'])->name('renew')
        ->middleware(['whmcs.own:domain,id', 'throttle:5,1']);
    Route::put('/{id}/nameservers', [DomainController::class, 'updateNameservers'])->name('nameservers.update')
        ->middleware('whmcs.own:domain,id');
    Route::post('/{id}/lock', [DomainController::class, 'toggleLock'])->name('lock.toggle')
        ->middleware('whmcs.own:domain,id');
    Route::post('/{id}/epp', [DomainController::class, 'requestEpp'])->name('epp')
        ->middleware(['whmcs.own:domain,id', 'throttle:3,1']);
    Route::post('/{id}/autorenew', [DomainController::class, 'toggleAutoRenew'])->name('autorenew')
        ->middleware('whmcs.own:domain,id');
    Route::put('/{id}/whois', [DomainController::class, 'updateWhoisContact'])->name('whois.update')
        ->middleware('whmcs.own:domain,id');
    Route::put('/{id}/dns', [DomainController::class, 'saveDnsRecords'])->name('dns.update')
        ->middleware('whmcs.own:domain,id');
    Route::post('/{id}/private-ns/register', [DomainController::class, 'registerPrivateNameserver'])->name('privatens.register')
        ->middleware(['whmcs.own:domain,id', 'throttle:10,1']);
    Route::post('/{id}/private-ns/modify', [DomainController::class, 'modifyPrivateNameserver'])->name('privatens.modify')
        ->middleware(['whmcs.own:domain,id', 'throttle:10,1']);
    Route::post('/{id}/private-ns/delete', [DomainController::class, 'deletePrivateNameserver'])->name('privatens.delete')
        ->middleware(['whmcs.own:domain,id', 'throttle:10,1']);
});

// ─── Currency ───────────────────────────────────────────────
Route::post('/currency/switch', [CurrencyController::class, 'switch'])->name('currency.switch');
Route::get('/currency/list', [CurrencyController::class, 'list'])->name('currency.list');

// ─── Invoices ───────────────────────────────────────────────
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('index');
    Route::get('/{id}', [InvoiceController::class, 'show'])->name('show')
        ->middleware('whmcs.own:invoice,id');
    Route::get('/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('pdf')
        ->middleware('whmcs.own:invoice,id');
    Route::post('/{id}/pay', [InvoiceController::class, 'pay'])->name('pay')
        ->middleware(['whmcs.own:invoice,id', 'throttle:10,1']);
    Route::post('/{id}/payment-method', [InvoiceController::class, 'updatePaymentMethod'])->name('paymentmethod')
        ->middleware(['whmcs.own:invoice,id', 'throttle:10,1']);
});

// ─── In-Portal Payments ────────────────────────────────────
Route::prefix('payment')->name('payment.')->group(function () {
    Route::post('/{id}/apply-credit', [PaymentController::class, 'applyCredit'])->name('applyCredit')
        ->middleware(['whmcs.own:invoice,id', 'throttle:10,1']);
    Route::post('/{id}/pay', [PaymentController::class, 'pay'])->name('pay')
        ->middleware(['whmcs.own:invoice,id', 'throttle:10,1']);
    Route::post('/{id}/upload-proof', [PaymentController::class, 'uploadPaymentProof'])->name('uploadProof')
        ->middleware(['whmcs.own:invoice,id', 'throttle:5,1']);
    // Callbacks are handled publicly in web.php (SSLCommerz needs no auth/CSRF)
});


// ─── Billing ────────────────────────────────────────────────
Route::prefix('billing')->name('billing.')->group(function () {
    Route::get('/transactions', [BillingController::class, 'transactions'])->name('transactions');
    Route::get('/credit', [BillingController::class, 'credit'])->name('credit');
    Route::post('/credit/add-funds', [BillingController::class, 'addFunds'])->name('credit.addFunds')
        ->middleware('throttle:5,1');

    Route::middleware('feature:quotes')->group(function () {
        Route::get('/quotes', [BillingController::class, 'quotes'])->name('quotes');
        Route::get('/quotes/{id}', [BillingController::class, 'showQuote'])->name('quotes.show')
            ->middleware('whmcs.own:quote,id');
        Route::post('/quotes/{id}/accept', [BillingController::class, 'acceptQuote'])->name('quotes.accept')
            ->middleware(['whmcs.own:quote,id', 'throttle:5,1']);
    });
});

// ─── Tickets ────────────────────────────────────────────────
Route::prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', [TicketController::class, 'index'])->name('index');
    Route::get('/create', [TicketController::class, 'create'])->name('create');
    Route::post('/', [TicketController::class, 'store'])->name('store')
        ->middleware('throttle:10,1');
    Route::get('/{id}', [TicketController::class, 'show'])->name('show')
        ->middleware('whmcs.own:ticket,id');
    Route::post('/{id}/reply', [TicketController::class, 'reply'])->name('reply')
        ->middleware(['whmcs.own:ticket,id', 'throttle:15,1']);
    Route::post('/{id}/close', [TicketController::class, 'close'])->name('close')
        ->middleware(['whmcs.own:ticket,id', 'throttle:5,1']);
});

// ─── Addons ─────────────────────────────────────────────────
Route::middleware('feature:addons')->prefix('addons')->name('addons.')->group(function () {
    Route::get('/', [AddonController::class, 'index'])->name('index');
});

// ─── Orders / Products ─────────────────────────────────────
Route::middleware('feature:orders')->prefix('orders')->name('orders.')->group(function () {
    Route::get('/products', [OrderController::class, 'products'])->name('products');
    Route::get('/products/{id}', [OrderController::class, 'productDetail'])->name('products.show');
    Route::post('/domain-check', [OrderController::class, 'checkDomainAvailability'])->name('domain.check');
    Route::get('/cart', [OrderController::class, 'cart'])->name('cart');
    Route::post('/cart/add', [OrderController::class, 'addToCart'])->name('cart.add');
    Route::delete('/cart/{index}', [OrderController::class, 'removeFromCart'])->name('cart.remove');
    Route::post('/cart/promo', [OrderController::class, 'applyPromo'])->name('cart.promo');
    Route::post('/checkout', [OrderController::class, 'checkout'])->name('checkout')
        ->middleware('throttle:15,1');
    Route::get('/', [OrderController::class, 'orders'])->name('index');
});

// ─── Knowledgebase ──────────────────────────────────────────
Route::middleware('feature:knowledgebase')->prefix('knowledgebase')->name('kb.')->group(function () {
    Route::get('/', [KnowledgebaseController::class, 'index'])->name('index');
    Route::get('/category/{id}', [KnowledgebaseController::class, 'category'])->name('category');
    Route::get('/article/{id}', [KnowledgebaseController::class, 'article'])->name('article');
});

// ─── Announcements ──────────────────────────────────────────
Route::middleware('feature:announcements')->prefix('announcements')->name('announcements.')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index'])->name('index');
    Route::get('/{id}', [AnnouncementController::class, 'show'])->name('show');
});

// ─── Downloads ──────────────────────────────────────────────
Route::middleware('feature:downloads')->prefix('downloads')->name('downloads.')->group(function () {
    Route::get('/', [DownloadController::class, 'index'])->name('index');
});

// ─── Account / Security ────────────────────────────────────
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/password', [AccountController::class, 'changePassword'])->name('password')
        ->middleware('throttle:5,1');
    Route::get('/contacts', [AccountController::class, 'contacts'])->name('contacts');
    Route::post('/contacts', [AccountController::class, 'storeContact'])->name('contacts.store');
    Route::delete('/contacts/{id}', [AccountController::class, 'deleteContact'])->name('contacts.destroy');
    Route::get('/security', [AccountController::class, 'security'])->name('security');
});

// ─── Affiliates ─────────────────────────────────────────────
Route::middleware('feature:affiliates')->prefix('affiliates')->name('affiliates.')->group(function () {
    Route::get('/', [AffiliateController::class, 'dashboard'])->name('dashboard');
});

// ─── SSO (Open in WHMCS) ───────────────────────────────────
Route::middleware('feature:sso')->group(function () {
    Route::get('/sso', [SsoController::class, 'redirect'])->name('sso');
});
