<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhmcsApiService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'firstname'   => 'required|string|max:255',
            'lastname'    => 'required|string|max:255',
            'email'       => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'phone'       => 'required|string|max:50',
            'companyname' => 'nullable|string|max:255',
            'address1'    => 'required|string|max:255',
            'address2'    => 'nullable|string|max:255',
            'city'        => 'required|string|max:255',
            'state'       => 'required|string|max:255',
            'postcode'    => 'required|string|max:20',
            'country'     => 'required|string|size:2',
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Register client in WHMCS
        $whmcs = app(WhmcsApiService::class);

        $whmcsData = [
            'firstname'   => $request->firstname,
            'lastname'    => $request->lastname,
            'email'       => $request->email,
            'phonenumber' => $request->phone,
            'companyname' => $request->companyname ?? '',
            'address1'    => $request->address1,
            'address2'    => $request->address2 ?? '',
            'city'        => $request->city,
            'state'       => $request->state,
            'postcode'    => $request->postcode,
            'country'     => $request->country,
            'password2'   => $request->password,
        ];

        try {
            $result = $whmcs->addClient($whmcsData);
        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Registration failed: ' . $e->getMessage()]);
        }

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['email' => $result['message'] ?? 'Failed to create account in WHMCS.']);
        }

        $whmcsClientId = (int) ($result['clientid'] ?? 0);

        // Create local user
        $user = User::create([
            'name'            => $request->firstname . ' ' . $request->lastname,
            'email'           => Str::lower($request->email),
            'password'        => Hash::make($request->password),
            'whmcs_client_id' => $whmcsClientId,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
