<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        Log::info('LOGIN STEP 1: Before authenticate', [
            'session_id' => $request->session()->getId(),
            'url_intended' => session('url.intended'),
            'session_keys' => array_keys(session()->all()),
        ]);

        $request->authenticate();

        Log::info('LOGIN STEP 2: After authenticate', [
            'session_id' => $request->session()->getId(),
            'url_intended' => session('url.intended'),
        ]);

        $request->session()->regenerate();

        Log::info('LOGIN STEP 3: After regenerate', [
            'session_id' => $request->session()->getId(),
            'url_intended' => session('url.intended'),
        ]);

        $intended = session()->pull('url.intended', route('dashboard', absolute: false));

        Log::info('LOGIN STEP 4: Final redirect', [
            'intended' => $intended,
            'has_oauth' => str_contains($intended, '/oauth/'),
        ]);

        // If redirecting to an OAuth authorize URL, use Inertia::location()
        // to force a full-page browser redirect (not an XHR Inertia visit),
        // because /oauth/* routes are outside the Inertia SPA.
        if (str_contains($intended, '/oauth/')) {
            return Inertia::location($intended);
        }

        return redirect($intended);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
