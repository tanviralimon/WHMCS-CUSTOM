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

        $result = $this->whmcs->getInvoices($clientId, $status, ($page - 1) * $perPage, $perPage, 'id', 'desc');

        $invoices = $result['invoices']['invoice'] ?? [];

        // When showing all statuses, prioritise unpaid/overdue at the top
        if (!$status) {
            $priority = ['Overdue' => 0, 'Unpaid' => 1, 'Payment Pending' => 2];
            usort($invoices, function ($a, $b) use ($priority) {
                $pa = $priority[$a['status']] ?? 9;
                $pb = $priority[$b['status']] ?? 9;
                if ($pa !== $pb) return $pa - $pb;
                // Within the same priority group, newest first (higher id = newer)
                return (int) $b['id'] - (int) $a['id'];
            });
        }

        return Inertia::render('Client/Invoices/Index', [
            'invoices' => $invoices,
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

        // Check which gateways we handle natively vs SSO fallback
        $supportedMap = config('payment.supported_gateways', []);
        foreach ($gateways as &$gw) {
            $handler = $supportedMap[strtolower($gw['module'] ?? '')] ?? null;
            $gw['native'] = $handler !== null; // true = we handle it directly, false = SSO fallback
        }

        // Get bank transfer info if banktransfer is a supported gateway
        $bankInfo = null;
        if (isset($supportedMap['banktransfer'])) {
            $bankConfig = $this->whmcs->getGatewayConfig('banktransfer');
            if (($bankConfig['result'] ?? '') === 'success' && !empty($bankConfig['settings'])) {
                $s = $bankConfig['settings'];
                $bankInfo = array_filter([
                    'bank_name'      => $s['bankname'] ?? $s['bank_name'] ?? '',
                    'account_name'   => $s['bankaccount'] ?? $s['account_name'] ?? $s['accname'] ?? '',
                    'account_number' => $s['accountnumber'] ?? $s['account_number'] ?? $s['accno'] ?? '',
                    'branch'         => $s['bankbranch'] ?? $s['branch'] ?? '',
                    'routing'        => $s['bankrouting'] ?? $s['routing'] ?? $s['routing_number'] ?? '',
                    'swift'          => $s['bankswift'] ?? $s['swift'] ?? '',
                    'iban'           => $s['bankiban'] ?? $s['iban'] ?? '',
                    'instructions'   => $s['instructions'] ?? $s['description'] ?? '',
                ]);
            }
        }

        // Get WHMCS ticket upload limits for payment proof
        $ticketUploadConfig = $this->whmcs->getTicketUploadConfig();

        // Check if payment proof already submitted for this invoice
        $proofSubmitted = $this->whmcs->hasPaymentProofTicket($clientId, $id);

        // Normalize transactions: WHMCS returns single object instead of array when only one transaction
        $rawTxns = $result['transactions']['transaction'] ?? [];
        if (!empty($rawTxns) && isset($rawTxns['id'])) {
            $rawTxns = [$rawTxns];
        }
        if (!isset($result['transactions'])) $result['transactions'] = [];
        $result['transactions']['transaction'] = array_values((array) $rawTxns);

        // Normalize items: same single-object issue
        $rawItems = $result['items']['item'] ?? [];
        if (!empty($rawItems) && isset($rawItems['id'])) {
            $rawItems = [$rawItems];
        }
        if (!isset($result['items'])) $result['items'] = [];
        $result['items']['item'] = array_values((array) $rawItems);

        return Inertia::render('Client/Invoices/Show', [
            'invoice'            => $result,
            'creditBalance'      => $creditBalance,
            'paymentMethods'     => $gateways,
            'bankInfo'           => $bankInfo,
            'ticketUploadConfig' => $ticketUploadConfig,
            'proofSubmitted'     => $proofSubmitted,
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
