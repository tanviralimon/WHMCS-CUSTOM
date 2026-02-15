<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WhmcsService $whmcs)
    {
        $clientId = $request->user()->whmcs_client_id;

        $profile   = $whmcs->getClientsDetails($clientId);
        $services  = $whmcs->getClientsProducts($clientId, 0, 5, 'Active');
        $invoices  = $whmcs->getInvoices($clientId, 'Unpaid', 0, 5);
        $tickets   = $whmcs->getTickets($clientId, '', 0, 5);
        $domains   = $whmcs->getClientsDomains($clientId, 0, 5, 'Active');

        // Count unpaid invoices
        $allUnpaid = $whmcs->getInvoices($clientId, 'Unpaid', 0, 1);
        $unpaidCount = (int) ($allUnpaid['totalresults'] ?? 0);

        // Count active services
        $allActive = $whmcs->getClientsProducts($clientId, 0, 1, 'Active');
        $activeCount = (int) ($allActive['totalresults'] ?? 0);

        // Count open tickets
        $allOpen = $whmcs->getTickets($clientId, 'Open', 0, 1);
        $openTicketCount = (int) ($allOpen['totalresults'] ?? 0);

        // Count domains
        $totalDomains = (int) ($domains['totalresults'] ?? 0);

        // Credit balance
        $creditBalance = $profile['credit'] ?? '0.00';

        return Inertia::render('Client/Dashboard', [
            'profile'        => $profile,
            'stats'          => [
                'activeServices'  => $activeCount,
                'unpaidInvoices'  => $unpaidCount,
                'openTickets'     => $openTicketCount,
                'totalDomains'    => $totalDomains,
                'creditBalance'   => $creditBalance,
            ],
            'services'       => $services['products']['product'] ?? [],
            'invoices'       => $invoices['invoices']['invoice'] ?? [],
            'tickets'        => $tickets['tickets']['ticket'] ?? [],
            'domains'        => $domains['domains']['domain'] ?? [],
            'features'       => config('client-features'),
        ]);
    }
}
