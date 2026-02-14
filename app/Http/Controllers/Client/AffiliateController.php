<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AffiliateController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function dashboard(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $result   = $this->whmcs->getAffiliates($clientId);

        return Inertia::render('Client/Affiliates/Dashboard', [
            'affiliate' => $result,
        ]);
    }
}
