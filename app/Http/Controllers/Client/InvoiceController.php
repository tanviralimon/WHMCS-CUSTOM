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

        $clientId = $request->user()->whmcs_client_id;

        // Get client credit balance
        $profile  = $this->whmcs->getClientsDetails($clientId);
        $creditBalance = (float) ($profile['credit'] ?? 0);

        // Get available payment methods from WHMCS
        $paymentMethods = $this->whmcs->getPaymentMethods();
        $gateways = $paymentMethods['paymentmethods']['paymentmethod'] ?? [];

        // Build SSO pay URL for unpaid invoices
        $payUrl = null;
        if ($result['status'] === 'Unpaid') {
            try {
                $sso = $this->whmcs->createClientSsoToken($clientId, 'clientarea:invoices');
                if (!empty($sso['redirect_url'])) {
                    $ssoUrl = $sso['redirect_url'];
                    $sep = str_contains($ssoUrl, '?') ? '&' : '?';
                    $payUrl = $ssoUrl . $sep . 'goto=' . urlencode('viewinvoice.php?id=' . $id);
                }
            } catch (\Exception $e) {
                // Fallback: direct WHMCS link (user may need to log in)
                $payUrl = rtrim(config('whmcs.base_url'), '/') . '/viewinvoice.php?id=' . $id;
            }
        }

        return Inertia::render('Client/Invoices/Show', [
            'invoice'        => $result,
            'creditBalance'  => $creditBalance,
            'paymentMethods' => $gateways,
            'payUrl'         => $payUrl,
        ]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        // Redirect to WHMCS invoice PDF download
        $url = rtrim(config('whmcs.base_url'), '/') . '/dl.php?type=i&id=' . $id;
        return redirect()->away($url);
    }

    /**
     * Update the payment method for an unpaid invoice.
     */
    public function updatePaymentMethod(Request $request, int $id)
    {
        $request->validate([
            'paymentmethod' => 'required|string',
        ]);

        $result = $this->whmcs->updateInvoicePaymentMethod($id, $request->paymentmethod);

        if (($result['result'] ?? '') === 'success') {
            return back()->with('success', 'Payment method updated.');
        }

        return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to update payment method.']);
    }

    /**
     * Redirect to WHMCS payment gateway for a specific invoice.
     */
    public function pay(Request $request, int $id)
    {
        $whmcsBase = rtrim(config('whmcs.base_url'), '/');
        $invoiceUrl = $whmcsBase . '/viewinvoice.php?id=' . $id;

        // Create SSO token to auto-login at WHMCS, then redirect to the invoice
        $clientId = $request->user()->whmcs_client_id;
        try {
            $sso = $this->whmcs->createClientSsoToken($clientId, 'clientarea:invoices');
            if (!empty($sso['redirect_url'])) {
                // The SSO redirect_url logs the user in and takes them to clientarea.
                // We append a goto param so WHMCS redirects to the specific invoice after SSO login.
                $ssoUrl = $sso['redirect_url'];
                $separator = str_contains($ssoUrl, '?') ? '&' : '?';
                return redirect()->away($ssoUrl . $separator . 'goto=' . urlencode('viewinvoice.php?id=' . $id));
            }
        } catch (\Exception $e) {
            // Fall through to direct link
        }

        return redirect()->away($invoiceUrl);
    }
}
