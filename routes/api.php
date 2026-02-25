<?php

use App\Http\Controllers\OidcController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OpenID Connect / OAuth 2.0 API Routes
|--------------------------------------------------------------------------
|
| These routes serve as the OIDC-compatible endpoints for orcus.one acting
| as an OAuth 2.0 / OpenID Connect provider for external services like
| Aurizor. All token-protected routes use the Passport 'api' guard.
|
*/

// ── UserInfo Endpoint (OIDC Standard) ─────────────────────────────────
Route::middleware('auth:api')->get('/userinfo', [OidcController::class, 'userinfo'])
    ->name('oidc.userinfo');
