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

        // Compute summary stats from the current result set
        $allInvoices = $result['invoices']['invoice'] ?? [];
        $stats = [
            'total'        => (int) ($result['totalresults'] ?? 0),
            'unpaid_count' => 0,
            'unpaid_total' => 0,
            'overdue_count' => 0,
            'paid_count'   => 0,
        ];
        foreach ($allInvoices as $inv) {
            $s = strtolower($inv['status'] ?? '');
            if ($s === 'unpaid') {
                $stats['unpaid_count']++;
                $stats['unpaid_total'] += (float) ($inv['balance'] ?? $inv['total'] ?? 0);
            } elseif ($s === 'overdue') {
                $stats['overdue_count']++;
                $stats['unpaid_total'] += (float) ($inv['balance'] ?? $inv['total'] ?? 0);
            } elseif ($s === 'paid') {
                $stats['paid_count']++;
            }
        }

        return Inertia::render('Client/Invoices/Index', [
            'invoices' => $invoices,
            'total'    => (int) ($result['totalresults'] ?? 0),
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
            'stats'    => $stats,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->whmcs->getInvoice($id);

        if (($result['result'] ?? '') !== 'success') {
            abort(404);
        }

        $clientId = $request->user()->whmcs_client_id;

        // Get client details (credit + billing address)
        $profile  = $this->whmcs->getClientsDetails($clientId);
        $creditBalance = (float) ($profile['credit'] ?? 0);

        // Extract client billing info for the invoice
        $clientDetails = [
            'name'      => trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? '')),
            'company'   => $profile['companyname'] ?? '',
            'email'     => $profile['email'] ?? $request->user()->email,
            'address1'  => $profile['address1'] ?? '',
            'address2'  => $profile['address2'] ?? '',
            'city'      => $profile['city'] ?? '',
            'state'     => $profile['state'] ?? '',
            'postcode'  => $profile['postcode'] ?? '',
            'country'   => $profile['country'] ?? '',
            'phone'     => $profile['phonenumber'] ?? '',
        ];

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

        return Inertia::render('Client/Invoices/Show', [
            'invoice'            => $result,
            'creditBalance'      => $creditBalance,
            'clientDetails'      => $clientDetails,
            'companyName'        => 'OrcusTech',
            'paymentMethods'     => $gateways,
            'bankInfo'           => $bankInfo,
            'ticketUploadConfig' => $ticketUploadConfig,
            'proofSubmitted'     => $proofSubmitted,
        ]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;
        $whmcsBase = rtrim(config('whmcs.base_url'), '/');

        // Strategy 1: Create SSO token and use it to fetch the PDF server-side
        try {
            $sso = $this->whmcs->createClientSsoToken($clientId, 'clientarea:invoices');
            if (!empty($sso['redirect_url'])) {
                // First hit the SSO URL to get the session cookie, then download the PDF
                $ch = curl_init();
                $cookieFile = tempnam(sys_get_temp_dir(), 'whmcs_sso_');

                // Step 1: Follow SSO redirect to establish session
                curl_setopt_array($ch, [
                    CURLOPT_URL => $sso['redirect_url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_COOKIEJAR => $cookieFile,
                    CURLOPT_COOKIEFILE => $cookieFile,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_USERAGENT => 'OrcusTech/1.0',
                ]);
                curl_exec($ch);

                // Step 2: Download the PDF with the authenticated session
                curl_setopt($ch, CURLOPT_URL, $whmcsBase . '/dl.php?type=i&id=' . $id);
                $pdfContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                @unlink($cookieFile);

                if ($httpCode === 200 && $pdfContent && str_contains($contentType, 'pdf')) {
                    return response($pdfContent, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="Invoice-' . $id . '.pdf"',
                        'Cache-Control' => 'no-cache',
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Fall through to SSO redirect
        }

        // Strategy 2: Redirect user through SSO to the PDF (browser will handle auth)
        try {
            $sso = $this->whmcs->createClientSsoToken($clientId, 'clientarea:invoices');
            if (!empty($sso['redirect_url'])) {
                $ssoUrl = $sso['redirect_url'];
                $sep = str_contains($ssoUrl, '?') ? '&' : '?';
                return redirect()->away($ssoUrl . $sep . 'goto=' . urlencode('dl.php?type=i&id=' . $id));
            }
        } catch (\Exception $e) {
            // Fall through to direct link
        }

        // Strategy 3: Direct link (will require WHMCS login)
        return redirect()->away($whmcsBase . '/dl.php?type=i&id=' . $id);
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
