<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WhmcsSsoController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    /**
     * Redirect to WHMCS for SSO login.
     * We build a URL that tells WHMCS to redirect back to us after auth.
     */
    public function redirect(Request $request)
    {
        $whmcsBase = rtrim(config('whmcs.base_url'), '/');
        $callbackUrl = route('sso.callback');

        // Redirect user to WHMCS login page with a return URL
        $loginUrl = $whmcsBase . '/dologin.php?goto=' . urlencode('clientarea.php?action=sso_redirect&returnurl=' . urlencode($callbackUrl));

        return redirect()->away($loginUrl);
    }

    /**
     * Handle SSO callback — user is already authenticated in WHMCS.
     * We use an SSO token approach: try to look up the client by email from a token.
     * Alternatively, if WHMCS passes back user info, we use that.
     */
    public function callback(Request $request)
    {
        // If WHMCS redirects back with an access_token or sso_token
        $token = $request->get('access_token') ?? $request->get('sso_token');

        if ($token) {
            return $this->loginWithToken($token, $request);
        }

        // Fallback: redirect to manual login
        return redirect()->route('login')->with('status', 'SSO login failed. Please sign in manually.');
    }

    /**
     * Auto-login endpoint: Given a WHMCS client ID signed by our server,
     * log the user in automatically. This is used from WHMCS hooks.
     */
    public function autoLogin(Request $request)
    {
        $request->validate([
            'email'     => 'required|email',
            'client_id' => 'required|integer',
            'token'     => 'required|string',
        ]);

        // Verify the token is a valid HMAC signature
        $expectedToken = hash_hmac('sha256', $request->email . ':' . $request->client_id, config('whmcs.api_secret'));

        if (!hash_equals($expectedToken, $request->token)) {
            return redirect()->route('login')->withErrors(['email' => 'Invalid SSO token.']);
        }

        $clientId = (int) $request->client_id;

        // Fetch client details from WHMCS to verify they exist
        try {
            $details = $this->whmcs->getClientsDetails($clientId);
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Could not verify account.']);
        }

        if (($details['result'] ?? '') !== 'success') {
            return redirect()->route('login')->withErrors(['email' => 'Account not found.']);
        }

        // Create or update local user
        $user = User::updateOrCreate(
            ['email' => Str::lower($request->email)],
            [
                'name'            => ($details['firstname'] ?? '') . ' ' . ($details['lastname'] ?? ''),
                'password'        => Hash::make(Str::random(32)),
                'whmcs_client_id' => $clientId,
            ]
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard');
    }

    protected function loginWithToken(string $token, Request $request)
    {
        // Attempt to decode the SSO token passed from WHMCS
        // This would work if WHMCS passes back client data
        return redirect()->route('login')->with('status', 'SSO login is being processed.');
    }
}
