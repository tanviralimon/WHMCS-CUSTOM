<?php

namespace App\Http\Controllers;

use App\Services\WhmcsApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceController extends Controller
{
    protected WhmcsApiService $whmcs;

    public function __construct(WhmcsApiService $whmcs)
    {
        $this->whmcs = $whmcs;
    }

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page = max(0, ((int) $request->get('page', 1)) - 1);

        $result = $this->whmcs->getClientsProducts($clientId, $page * 25, 25);

        return Inertia::render('Services/Index', [
            'services' => $result['products']['product'] ?? [],
            'total' => (int) ($result['totalresults'] ?? 0),
            'page' => $page + 1,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;

        $result = $this->whmcs->getProduct($clientId, $id);
        $service = ($result['products']['product'] ?? [null])[0] ?? null;

        if (!$service) {
            abort(404);
        }

        return Inertia::render('Services/Show', [
            'service' => $service,
        ]);
    }
}
