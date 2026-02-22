<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'companyName'        => 'Orcus Technology',
            'paymentMethods'     => $gateways,
            'bankInfo'           => $bankInfo,
            'ticketUploadConfig' => $ticketUploadConfig,
            'proofSubmitted'     => $proofSubmitted,
        ]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        $result = $this->whmcs->getInvoice($id);

        if (($result['result'] ?? '') !== 'success') {
            abort(404);
        }

        $clientId = $request->user()->whmcs_client_id;

        // Get client billing details
        $profile = $this->whmcs->getClientsDetails($clientId);
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

        // Company details for the invoice header
        $companyDetails = [
            'address1'  => config('invoice.company_address1', ''),
            'address2'  => config('invoice.company_address2', ''),
            'city'      => config('invoice.company_city', ''),
            'state'     => config('invoice.company_state', ''),
            'postcode'  => config('invoice.company_postcode', ''),
            'country'   => config('invoice.company_country', ''),
            'phone'     => config('invoice.company_phone', ''),
            'email'     => config('invoice.company_email', 'support@orcustech.com'),
            'taxId'     => config('invoice.company_tax_id', ''),
        ];

        // Resolve payment method display name
        $paymentMethodName = $result['paymentmethod'] ?? '—';
        try {
            $methods = $this->whmcs->getPaymentMethods();
            $gateways = $methods['paymentmethods']['paymentmethod'] ?? [];
            foreach ($gateways as $gw) {
                if (($gw['module'] ?? '') === $result['paymentmethod']) {
                    $paymentMethodName = $gw['displayname'] ?? $gw['module'];
                    break;
                }
            }
        } catch (\Exception $e) {
            // keep raw name
        }

        // ── Resolve currency code from GetCurrencies API ──
        // GetInvoice does NOT return currency fields, so we look up the
        // client's currency ID from their profile, then match it against
        // the system currencies list.
        // NOTE: We always use "amount CODE" format (e.g. "1,000.00 BDT")
        // because some currency symbols like ৳ (BDT) cannot be rendered
        // by DomPDF fonts, causing broken □ characters.
        $clientCurrencyId = (int) ($profile['currency'] ?? 1);
        $currencyCode = '';

        try {
            $currencies   = $this->whmcs->getCurrencies();
            $currencyList = $currencies['currencies']['currency'] ?? [];
            // Handle single-currency WHMCS (not wrapped in indexed array)
            if (isset($currencyList['id'])) {
                $currencyList = [$currencyList];
            }
            foreach ($currencyList as $curr) {
                if ((int) ($curr['id'] ?? 0) === $clientCurrencyId) {
                    $currencyCode = strtoupper($curr['code'] ?? '');
                    break;
                }
            }
        } catch (\Exception $e) {
            // Fallback: use currency_code from client profile if available
            $currencyCode = strtoupper($profile['currency_code'] ?? '');
        }

        // Use code-based suffix for PDF: "1,000.00 BDT"
        $currencyPrefix = '';
        $currencySuffix = $currencyCode ? ' ' . $currencyCode : '';

        $data = [
            'invoice'           => $result,
            'clientDetails'     => $clientDetails,
            'companyName'       => 'Orcus Technology',
            'companyDetails'    => $companyDetails,
            'paymentMethodName' => $paymentMethodName,
            'currencyPrefix'    => $currencyPrefix,
            'currencySuffix'    => $currencySuffix,
            'currencyCode'      => $currencyCode,
        ];

        $invoiceNum = $result['invoicenum'] ?: $result['invoiceid'];

        $pdf = Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download("Invoice-{$invoiceNum}.pdf");
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
