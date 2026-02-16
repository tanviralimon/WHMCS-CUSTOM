<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    /**
     * Apply account credit to an invoice.
     */
    public function applyCredit(Request $request, int $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoice = $this->whmcs->getInvoice($id);
        if (($invoice['result'] ?? '') !== 'success') {
            return back()->withErrors(['payment' => 'Invoice not found.']);
        }
        if ($invoice['status'] !== 'Unpaid') {
            return back()->withErrors(['payment' => 'This invoice is already paid.']);
        }

        $balance = (float) ($invoice['balance'] ?? $invoice['total']);
        $amount  = min((float) $request->amount, $balance);

        try {
            $result = $this->whmcs->applyCredit($id, $amount);
            if (($result['result'] ?? '') === 'success') {
                return back()->with('success', 'Credit applied successfully.');
            }
            return back()->withErrors(['payment' => $result['message'] ?? 'Failed to apply credit.']);
        } catch (\Exception $e) {
            Log::error('Apply credit failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return back()->withErrors(['payment' => 'Failed to apply credit. Please try again.']);
        }
    }

    /**
     * Universal pay endpoint.
     * Routes to the correct gateway handler based on the selected module.
     * Returns JSON with { url } for the frontend to redirect to.
     */
    public function pay(Request $request, int $id)
    {
        $request->validate(['gateway' => 'required|string']);

        $gatewayModule = $request->gateway;
        $invoice = $this->whmcs->getInvoice($id);

        if (($invoice['result'] ?? '') !== 'success' || $invoice['status'] !== 'Unpaid') {
            return response()->json(['error' => 'Invalid or already paid invoice.'], 400);
        }

        // Check if we have a native handler for this gateway
        $supported = config('payment.supported_gateways', []);
        $handler = $supported[strtolower($gatewayModule)] ?? null;

        // First update the invoice payment method in WHMCS to match selection
        $this->whmcs->updateInvoicePaymentMethod($id, $gatewayModule);

        if ($handler === 'stripe') {
            return $this->handleStripe($request, $id, $invoice);
        }

        if ($handler === 'sslcommerz') {
            return $this->handleSslcommerz($request, $id, $invoice);
        }

        // Fallback: SSO redirect to WHMCS invoice page
        return $this->handleSsoFallback($request, $id);
    }

    /**
     * Stripe Checkout: create session and return URL.
     */
    private function handleStripe(Request $request, int $id, array $invoice)
    {
        $secretKey = config('payment.stripe.secret_key');
        if (empty($secretKey)) {
            return response()->json(['error' => 'Stripe is not configured. Please contact support.'], 500);
        }

        $balance = (float) ($invoice['balance'] ?? $invoice['total']);
        $currency = strtolower(config('payment.stripe.currency', 'usd'));

        try {
            $stripe = new \Stripe\StripeClient($secretKey);
            $description = 'Invoice #' . ($invoice['invoicenum'] ?? $id);

            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => $currency,
                        'product_data' => [
                            'name'        => $description,
                            'description' => 'Payment for ' . $description,
                        ],
                        'unit_amount' => (int) round($balance * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('client.payment.callback', ['id' => $id, 'gateway' => 'stripe']) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('client.invoices.show', $id),
                'metadata'    => [
                    'invoice_id' => $id,
                    'client_id'  => $request->user()->whmcs_client_id,
                ],
                'customer_email' => $request->user()->email,
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            Log::error('Stripe session failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create Stripe session. ' . $e->getMessage()], 500);
        }
    }

    /**
     * SSLCommerz: create session and return gateway URL.
     */
    private function handleSslcommerz(Request $request, int $id, array $invoice)
    {
        $storeId   = config('payment.sslcommerz.store_id');
        $storePass = config('payment.sslcommerz.store_password');
        $sandbox   = config('payment.sslcommerz.sandbox', false);

        if (empty($storeId) || empty($storePass)) {
            return response()->json(['error' => 'SSLCommerz is not configured. Please contact support.'], 500);
        }

        $balance = (float) ($invoice['balance'] ?? $invoice['total']);
        $user = $request->user();

        $apiUrl = $sandbox
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

        $postData = [
            'store_id'     => $storeId,
            'store_passwd' => $storePass,
            'total_amount' => $balance,
            'currency'     => strtoupper($invoice['currencycode'] ?? 'BDT'),
            'tran_id'      => 'INV' . $id . '_' . time(),
            'success_url'  => route('client.payment.callback', ['id' => $id, 'gateway' => 'sslcommerz']),
            'fail_url'     => route('client.payment.callback', ['id' => $id, 'gateway' => 'sslcommerz']),
            'cancel_url'   => route('client.invoices.show', $id),
            'ipn_url'      => route('client.payment.callback', ['id' => $id, 'gateway' => 'sslcommerz']),
            'cus_name'     => $user->name ?? 'Customer',
            'cus_email'    => $user->email,
            'cus_phone'    => '0000000000',
            'cus_add1'     => 'N/A',
            'cus_city'     => 'N/A',
            'cus_country'  => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name'    => 'Invoice #' . ($invoice['invoicenum'] ?? $id),
            'product_category' => 'Hosting',
            'product_profile'  => 'non-physical-goods',
            'value_a'     => $id,               // invoice_id
            'value_b'     => $user->whmcs_client_id, // client_id
        ];

        try {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => !$sandbox,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if (!empty($result['GatewayPageURL'])) {
                return response()->json(['url' => $result['GatewayPageURL']]);
            }

            Log::error('SSLCommerz session failed', ['invoice' => $id, 'response' => $result]);
            return response()->json(['error' => $result['failedreason'] ?? 'Failed to create SSLCommerz session.'], 500);
        } catch (\Exception $e) {
            Log::error('SSLCommerz error', ['invoice' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Payment gateway error. Please try again.'], 500);
        }
    }

    /**
     * Fallback: SSO redirect to WHMCS invoice page.
     */
    private function handleSsoFallback(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;

        try {
            $sso = $this->whmcs->createClientSsoToken($clientId, 'clientarea:invoices');
            if (!empty($sso['redirect_url'])) {
                $ssoUrl = $sso['redirect_url'];
                $sep = str_contains($ssoUrl, '?') ? '&' : '?';
                $url = $ssoUrl . $sep . 'goto=' . urlencode('viewinvoice.php?id=' . $id);
                return response()->json(['url' => $url]);
            }
        } catch (\Exception $e) {
            // fall through
        }

        $url = rtrim(config('whmcs.base_url'), '/') . '/viewinvoice.php?id=' . $id;
        return response()->json(['url' => $url]);
    }

    /**
     * Universal payment callback handler.
     * Handles success callbacks from Stripe, SSLCommerz, etc.
     */
    public function callback(Request $request, int $id, string $gateway)
    {
        if ($gateway === 'stripe') {
            return $this->handleStripeCallback($request, $id);
        }

        if ($gateway === 'sslcommerz') {
            return $this->handleSslcommerzCallback($request, $id);
        }

        return redirect()->route('client.invoices.show', $id);
    }

    /**
     * Stripe success callback.
     */
    private function handleStripeCallback(Request $request, int $id)
    {
        $sessionId = $request->get('session_id');
        if (!$sessionId) {
            return redirect()->route('client.invoices.show', $id)
                ->withErrors(['payment' => 'Invalid payment session.']);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('payment.stripe.secret_key'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return redirect()->route('client.invoices.show', $id)
                    ->withErrors(['payment' => 'Payment was not completed.']);
            }

            $amount = $session->amount_total / 100;
            $transId = $session->payment_intent;
            $fees = 0;

            try {
                $pi = $stripe->paymentIntents->retrieve($transId);
                if ($pi->latest_charge) {
                    $charge = $stripe->charges->retrieve($pi->latest_charge);
                    if ($charge->balance_transaction) {
                        $bt = $stripe->balanceTransactions->retrieve($charge->balance_transaction);
                        $fees = ($bt->fee ?? 0) / 100;
                    }
                }
            } catch (\Exception $e) {
                // Non-critical
            }

            $this->whmcs->addInvoicePayment($id, $transId, $amount, 'Stripe', $fees);

            return redirect()->route('client.invoices.show', $id)
                ->with('success', 'Payment completed successfully!');
        } catch (\Exception $e) {
            Log::error('Stripe callback failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('client.invoices.show', $id)
                ->withErrors(['payment' => 'Payment verification failed. Contact support if you were charged.']);
        }
    }

    /**
     * SSLCommerz success/fail callback.
     */
    private function handleSslcommerzCallback(Request $request, int $id)
    {
        $status = $request->input('status');
        $tranId = $request->input('tran_id', '');
        $amount = (float) $request->input('amount', 0);
        $valId  = $request->input('val_id', '');

        if ($status !== 'VALID' && $status !== 'VALIDATED') {
            $msg = $status === 'FAILED' ? 'Payment failed.' : 'Payment was cancelled.';
            return redirect()->route('client.invoices.show', $id)
                ->withErrors(['payment' => $msg]);
        }

        // Validate with SSLCommerz
        $storeId   = config('payment.sslcommerz.store_id');
        $storePass = config('payment.sslcommerz.store_password');
        $sandbox   = config('payment.sslcommerz.sandbox', false);

        $validateUrl = $sandbox
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';

        $validateUrl .= '?val_id=' . urlencode($valId) . '&store_id=' . urlencode($storeId) . '&store_passwd=' . urlencode($storePass) . '&format=json';

        try {
            $ch = curl_init($validateUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => !$sandbox,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            if (($result['status'] ?? '') === 'VALID' || ($result['status'] ?? '') === 'VALIDATED') {
                $validAmount = (float) ($result['amount'] ?? $amount);
                $this->whmcs->addInvoicePayment($id, $tranId, $validAmount, 'SSLCommerz');

                return redirect()->route('client.invoices.show', $id)
                    ->with('success', 'Payment completed successfully!');
            }

            return redirect()->route('client.invoices.show', $id)
                ->withErrors(['payment' => 'Payment validation failed. Contact support if you were charged.']);
        } catch (\Exception $e) {
            Log::error('SSLCommerz validation failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('client.invoices.show', $id)
                ->withErrors(['payment' => 'Payment verification error. Contact support.']);
        }
    }
}
