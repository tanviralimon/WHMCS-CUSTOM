<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $status   = $request->get('status', '');
        $perPage  = 25;

        $result = $this->whmcs->getInvoices($clientId, $status, ($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Invoices/Index', [
            'invoices' => $result['invoices']['invoice'] ?? [],
            'total'    => (int) ($result['totalresults'] ?? 0),
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->whmcs->getInvoice($id);

        if (($result['result'] ?? '') !== 'success') {
            abort(404);
        }

        // Build WHMCS pay URL — this redirects to the payment gateway
        $whmcsBase = rtrim(config('whmcs.base_url'), '/');
        $payUrl = $whmcsBase . '/viewinvoice.php?id=' . $id;

        // Get available payment methods
        $paymentMethods = $this->whmcs->getPaymentMethods();

        return Inertia::render('Client/Invoices/Show', [
            'invoice'        => $result,
            'payUrl'         => $payUrl,
            'paymentMethods' => $paymentMethods['paymentmethods']['paymentmethod'] ?? [],
        ]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        // Redirect to WHMCS invoice PDF download
        $url = rtrim(config('whmcs.base_url'), '/') . '/dl.php?type=i&id=' . $id;
        return redirect()->away($url);
    }

    /**
     * Redirect to WHMCS payment gateway for a specific invoice.
     */
    public function pay(Request $request, int $id)
    {
        $whmcsBase = rtrim(config('whmcs.base_url'), '/');

        // If client has SSO enabled, create an SSO token to auto-login at WHMCS
        if (config('client-features.sso')) {
            $clientId = $request->user()->whmcs_client_id;
            try {
                $sso = $this->whmcs->createSsoToken($clientId, 'clientarea:invoices');
                if (!empty($sso['redirect_url'])) {
                    // Redirect to WHMCS SSO which will then redirect to the invoice
                    return redirect()->away($sso['redirect_url']);
                }
            } catch (\Exception $e) {
                // Fall through to direct link
            }
        }

        return redirect()->away($whmcsBase . '/viewinvoice.php?id=' . $id);
    }
}
