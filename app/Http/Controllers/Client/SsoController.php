<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function redirect(Request $request)
    {
        if (!config('client-features.sso')) {
            abort(404);
        }

        $clientId    = $request->user()->whmcs_client_id;
        $destination = $request->get('destination', 'clientarea:services');

        $result = $this->whmcs->createClientSsoToken($clientId, $destination);

        if (($result['result'] ?? '') === 'success' && !empty($result['redirect_url'])) {
            return redirect()->away($result['redirect_url']);
        }

        // Fallback: redirect to WHMCS login page
        return redirect()->away(rtrim(config('whmcs.base_url'), '/') . '/clientarea.php');
    }
}
