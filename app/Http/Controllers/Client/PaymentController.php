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

        if ($handler === 'banktransfer') {
            return $this->handleBankTransfer($request, $id, $invoice);
        }

        // Fallback: SSO redirect to WHMCS invoice page
        return $this->handleSsoFallback($request, $id);
    }

    /**
     * Stripe Checkout: create session and return URL.
     * Pulls Stripe credentials directly from WHMCS gateway module configuration.
     */
    private function handleStripe(Request $request, int $id, array $invoice)
    {
        // 1. Try .env override first (full sk_live_* / sk_test_* key)
        $secretKey = config('payment.stripe_secret_key');

        Log::info('Stripe key debug', [
            'from_env' => $secretKey ? (substr($secretKey, 0, 7) . '...' . substr($secretKey, -4)) : 'EMPTY',
            'env_raw'  => env('STRIPE_SECRET_KEY') ? 'SET' : 'NOT SET',
        ]);

        // 2. Fall back to WHMCS gateway config
        if (empty($secretKey)) {
            $stripeConfig = $this->getStripeCredentials();
            $secretKey = $stripeConfig['secret_key'] ?? null;
            Log::info('Stripe fallback to WHMCS key', [
                'key_prefix' => $secretKey ? substr($secretKey, 0, 7) : 'EMPTY',
            ]);
        }

        // 3. If key is a restricted key (rk_*), it can't create Checkout Sessions
        //    → fall back to SSO so WHMCS handles the payment natively
        if (empty($secretKey) || str_starts_with($secretKey, 'rk_')) {
            Log::info('Stripe falling back to SSO', [
                'reason' => empty($secretKey) ? 'no key' : 'restricted key (rk_*)',
                'invoice' => $id,
            ]);
            if (!empty($secretKey)) {
                Log::info('Stripe key is restricted (rk_*), falling back to SSO payment', ['invoice' => $id]);
            }
            return $this->handleSsoFallback($request, $id);
        }

        $balance = (float) ($invoice['balance'] ?? $invoice['total']);
        $currency = strtolower($invoice['currencycode'] ?? '');

        // WHMCS GetInvoice doesn't return currencycode — detect from currencyprefix
        if (empty($currency)) {
            $prefix = $invoice['currencyprefix'] ?? '';
            $currency = $this->detectCurrencyCode($prefix, $request->user()->whmcs_client_id);
        }

        // Invoice number: use invoicenum if set, otherwise the invoice ID
        $invoiceNum = !empty($invoice['invoicenum']) ? $invoice['invoicenum'] : (string) $id;

        Log::info('Stripe session params', [
            'invoice'  => $id,
            'balance'  => $balance,
            'currency' => $currency,
            'invoicenum' => $invoiceNum,
            'raw_currencycode' => $invoice['currencycode'] ?? 'NOT SET',
            'raw_currencyprefix' => $invoice['currencyprefix'] ?? 'NOT SET',
        ]);

        try {
            $stripe = new \Stripe\StripeClient($secretKey);
            $description = 'Invoice #' . $invoiceNum;

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
            // If permissions error, fall back to SSO
            if (str_contains($e->getMessage(), 'permission') || str_contains($e->getMessage(), 'not have the required')) {
                Log::info('Stripe key lacks Checkout permission, falling back to SSO', ['invoice' => $id]);
                return $this->handleSsoFallback($request, $id);
            }
            Log::error('Stripe session failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create Stripe session. ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Stripe API credentials from WHMCS gateway configuration.
     * Handles both 'stripe' and 'stripe_checkout' module names.
     * Supports testMode toggle (live vs test keys).
     *
     * @return array|null  ['secret_key' => '...', 'publishable_key' => '...'] or null if not configured
     */
    private function getStripeCredentials(): ?array
    {
        // Try the stripe module names that WHMCS commonly uses
        $moduleNames = ['stripe', 'stripe_checkout', 'stripecheckout'];

        foreach ($moduleNames as $module) {
            $config = $this->whmcs->getGatewayConfig($module);

            if (($config['result'] ?? '') !== 'success' || empty($config['settings'])) {
                continue;
            }

            $settings = $config['settings'];

            // WHMCS Stripe modules typically have these field names:
            // secretKey / testSecretKey / publishableKey / testPublishableKey / testMode
            // OR: live_secret_key / test_secret_key / live_publishable_key / test_publishable_key
            $isTestMode = in_array(($settings['testMode'] ?? ''), ['on', '1', 'yes', true], true);

            // Try common Stripe module field naming patterns
            $secretKey = null;
            $publishableKey = null;

            if ($isTestMode) {
                $secretKey = $settings['testSecretKey'] ?? $settings['test_secret_key'] ?? null;
                $publishableKey = $settings['testPublishableKey'] ?? $settings['test_publishable_key'] ?? null;
            }

            if (empty($secretKey)) {
                $secretKey = $settings['secretKey'] ?? $settings['live_secret_key'] ?? $settings['secret_key'] ?? null;
            }
            if (empty($publishableKey)) {
                $publishableKey = $settings['publishableKey'] ?? $settings['live_publishable_key'] ?? $settings['publishable_key'] ?? null;
            }

            if (!empty($secretKey)) {
                Log::info('Stripe credentials loaded from WHMCS', ['module' => $module, 'testMode' => $isTestMode]);
                return [
                    'secret_key'      => $secretKey,
                    'publishable_key' => $publishableKey ?? '',
                    'test_mode'       => $isTestMode,
                ];
            }
        }

        return null;
    }

    /**
     * SSLCommerz: create session and return gateway URL.
     * Pulls SSLCommerz credentials from WHMCS gateway module configuration.
     */
    private function handleSslcommerz(Request $request, int $id, array $invoice)
    {
        $sslConfig = $this->getSslcommerzCredentials();
        if (!$sslConfig) {
            return response()->json(['error' => 'SSLCommerz is not configured in WHMCS. Please contact support.'], 500);
        }

        $storeId   = $sslConfig['store_id'];
        $storePass = $sslConfig['store_password'];
        $sandbox   = $sslConfig['sandbox'];

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
            'value_a'     => $id,
            'value_b'     => $user->whmcs_client_id,
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
     * Get SSLCommerz credentials from WHMCS gateway configuration.
     *
     * @return array|null  ['store_id' => '...', 'store_password' => '...', 'sandbox' => bool] or null
     */
    private function getSslcommerzCredentials(): ?array
    {
        $moduleNames = ['sslcommerz', 'sslcommerzs'];

        foreach ($moduleNames as $module) {
            $config = $this->whmcs->getGatewayConfig($module);

            if (($config['result'] ?? '') !== 'success' || empty($config['settings'])) {
                continue;
            }

            $settings = $config['settings'];

            // Actual WHMCS SSLCommerz field names: store_id, store_password, testmode
            $storeId   = $settings['store_id'] ?? $settings['storeId'] ?? $settings['storeid'] ?? null;
            $storePass = $settings['store_password'] ?? $settings['storePassword'] ?? $settings['store_passwd'] ?? null;
            $sandbox   = in_array(($settings['testmode'] ?? $settings['testMode'] ?? $settings['sandbox'] ?? ''), ['on', '1', 'yes', true], true);

            if (!empty($storeId) && !empty($storePass)) {
                Log::info('SSLCommerz credentials loaded from WHMCS', ['module' => $module, 'sandbox' => $sandbox]);
                return [
                    'store_id'       => $storeId,
                    'store_password' => $storePass,
                    'sandbox'        => $sandbox,
                ];
            }
        }

        return null;
    }

    /**
     * Bank Transfer: just update payment method, no redirect needed.
     * The invoice already shows bank details from WHMCS.
     */
    private function handleBankTransfer(Request $request, int $id, array $invoice)
    {
        // Payment method is already updated in pay() above.
        // Return a special response telling frontend to reload the page with a message.
        return response()->json([
            'message' => 'Payment method updated to Bank Transfer. Please follow the bank details shown on the invoice to complete your payment.',
            'reload' => true,
        ]);
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
     * Note: no session available (middleware excluded to prevent cross-site session overwrite).
     * Uses query params for status messages instead of flash.
     */
    private function handleStripeCallback(Request $request, int $id)
    {
        $sessionId = $request->get('session_id');
        if (!$sessionId) {
            return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('Invalid payment session.'));
        }

        try {
            // Use the same key resolution as handleStripe() — .env first, then WHMCS
            $secretKey = config('payment.stripe_secret_key');
            if (empty($secretKey)) {
                $stripeConfig = $this->getStripeCredentials();
                $secretKey = $stripeConfig['secret_key'] ?? null;
            }
            if (empty($secretKey)) {
                return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('Stripe configuration error. Contact support.'));
            }

            $stripe = new \Stripe\StripeClient($secretKey);
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('Payment was not completed.'));
            }

            // Check if invoice is already paid (prevents duplicate recording)
            $invoice = $this->whmcs->getInvoice($id);
            if (($invoice['status'] ?? '') === 'Paid') {
                return redirect(route('client.invoices.show', $id) . '?payment_success=1');
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

            return redirect(route('client.invoices.show', $id) . '?payment_success=1');
        } catch (\Exception $e) {
            Log::error('Stripe callback failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('Payment verification failed. Contact support if you were charged.'));
        }
    }

    /**
     * SSLCommerz success/fail callback.
     * Note: no session available (middleware excluded to prevent cross-site session overwrite).
     */
    private function handleSslcommerzCallback(Request $request, int $id)
    {
        $status = $request->input('status');
        $tranId = $request->input('tran_id', '');
        $amount = (float) $request->input('amount', 0);
        $valId  = $request->input('val_id', '');

        if ($status !== 'VALID' && $status !== 'VALIDATED') {
            $msg = $status === 'FAILED' ? 'Payment failed.' : 'Payment was cancelled.';
            return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode($msg));
        }

        // Validate with SSLCommerz using credentials from WHMCS
        $sslConfig = $this->getSslcommerzCredentials();
        if (!$sslConfig) {
            return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('SSLCommerz configuration error. Contact support.'));
        }

        $storeId   = $sslConfig['store_id'];
        $storePass = $sslConfig['store_password'];
        $sandbox   = $sslConfig['sandbox'];

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
                // Check if invoice is already paid (prevents duplicate recording
                // from success_url + IPN hitting this endpoint multiple times)
                $invoice = $this->whmcs->getInvoice($id);
                if (($invoice['status'] ?? '') !== 'Paid') {
                    $validAmount = (float) ($result['amount'] ?? $amount);
                    $this->whmcs->addInvoicePayment($id, $tranId, $validAmount, 'SSLCommerz');
                }

                return redirect(route('client.invoices.show', $id) . '?payment_success=1');
            }

            return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('Payment validation failed. Contact support if you were charged.'));
        } catch (\Exception $e) {
            Log::error('SSLCommerz validation failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return redirect(route('client.invoices.show', $id) . '?payment_error=' . urlencode('Payment verification error. Contact support.'));
        }
    }

    /**
     * Detect ISO 4217 currency code from WHMCS currency prefix/symbol.
     * Falls back to fetching the client's currency from WHMCS.
     */
    private function detectCurrencyCode(string $prefix, int $clientId): string
    {
        // Common symbol → ISO code mapping
        $symbolMap = [
            '$'  => 'usd',
            'US$' => 'usd',
            '£'  => 'gbp',
            '€'  => 'eur',
            '৳'  => 'bdt',
            'BDT' => 'bdt',
            '¥'  => 'jpy',
            '₹'  => 'inr',
            'INR' => 'inr',
            'A$' => 'aud',
            'C$' => 'cad',
            'R$' => 'brl',
            '₱'  => 'php',
            'RM' => 'myr',
            'S$' => 'sgd',
            'kr' => 'sek',
            'CHF' => 'chf',
            'zł' => 'pln',
            '₫'  => 'vnd',
            '₺'  => 'try',
            '₩'  => 'krw',
            'R'  => 'zar',
        ];

        $trimmed = trim($prefix);
        if (isset($symbolMap[$trimmed])) {
            return $symbolMap[$trimmed];
        }

        // If prefix is already an ISO code (3 letters), use it directly
        if (preg_match('/^[A-Za-z]{3}$/', $trimmed)) {
            return strtolower($trimmed);
        }

        // Last resort: fetch client profile to get currency
        try {
            $profile = $this->whmcs->getClientsDetails($clientId);
            $currencyCode = $profile['currency_code'] ?? $profile['currencycode'] ?? '';
            if (!empty($currencyCode)) {
                return strtolower($currencyCode);
            }
            // Try to get from currency ID
            $currencyId = $profile['currency'] ?? $profile['currencyid'] ?? null;
            if ($currencyId) {
                $currencies = $this->whmcs->getCurrencies();
                foreach (($currencies['currencies']['currency'] ?? []) as $c) {
                    if (($c['id'] ?? '') == $currencyId) {
                        return strtolower($c['code'] ?? 'usd');
                    }
                }
            }
        } catch (\Exception $e) {
            // fall through
        }

        return 'usd'; // absolute fallback
    }
}
