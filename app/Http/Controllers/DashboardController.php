<?php

namespace App\Http\Controllers;

use App\Services\WhmcsApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WhmcsApiService $whmcs)
    {
        $clientId = $request->user()->whmcs_client_id;

        $profile = $whmcs->getClientsDetails($clientId);
        $services = $whmcs->getClientsProducts($clientId, 0, 5);
        $invoices = $whmcs->getInvoices($clientId, '', 0, 5);
        $tickets = $whmcs->getTickets($clientId, '', 0, 5);

        return Inertia::render('Dashboard', [
            'profile' => $profile,
            'services' => $services['products']['product'] ?? [],
            'totalServices' => (int) ($services['totalresults'] ?? 0),
            'invoices' => $invoices['invoices']['invoice'] ?? [],
            'totalInvoices' => (int) ($invoices['totalresults'] ?? 0),
            'tickets' => $tickets['tickets']['ticket'] ?? [],
            'totalTickets' => (int) ($tickets['totalresults'] ?? 0),
        ]);
    }
}
