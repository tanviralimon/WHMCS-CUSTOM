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

        $clientId = $request->user()->whmcs_client_id;

        // Verify the invoice belongs to this client and is unpaid
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
     * Create a Stripe Checkout session for an invoice.
     * Returns JSON with the checkout session URL.
     */
    public function createStripeSession(Request $request, int $id)
    {
        if (!config('payment.stripe.enabled')) {
            return response()->json(['error' => 'Card payments are not available.'], 400);
        }

        $clientId = $request->user()->whmcs_client_id;

        // Verify invoice
        $invoice = $this->whmcs->getInvoice($id);
        if (($invoice['result'] ?? '') !== 'success' || $invoice['status'] !== 'Unpaid') {
            return response()->json(['error' => 'Invalid or already paid invoice.'], 400);
        }

        $balance = (float) ($invoice['balance'] ?? $invoice['total']);
        $currency = strtolower(config('payment.stripe.currency', 'usd'));

        try {
            $stripe = new \Stripe\StripeClient(config('payment.stripe.secret_key'));

            // Build line item description
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
                        'unit_amount' => (int) round($balance * 100), // Stripe uses cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('client.payment.stripe.success', $id) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('client.invoices.show', $id),
                'metadata'    => [
                    'invoice_id' => $id,
                    'client_id'  => $clientId,
                ],
                'customer_email' => $request->user()->email,
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            Log::error('Stripe session creation failed', ['invoice' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create payment session. Please try again.'], 500);
        }
    }

    /**
     * Handle Stripe Checkout success callback.
     * Verifies payment and records it in WHMCS.
     */
    public function stripeSuccess(Request $request, int $id)
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId || !config('payment.stripe.enabled')) {
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

            // Record payment in WHMCS
            $amount = $session->amount_total / 100; // Convert from cents
            $transId = $session->payment_intent;
            $fees = 0;

            // Try to get Stripe fees from the payment intent
            try {
                $paymentIntent = $stripe->paymentIntents->retrieve($transId);
                if ($paymentIntent->latest_charge) {
                    $charge = $stripe->charges->retrieve($paymentIntent->latest_charge);
                    if ($charge->balance_transaction) {
                        $bt = $stripe->balanceTransactions->retrieve($charge->balance_transaction);
                        $fees = ($bt->fee ?? 0) / 100;
                    }
                }
            } catch (\Exception $e) {
                // Non-critical — fees will be 0
            }

            $this->whmcs->addInvoicePayment($id, $transId, $amount, 'Stripe', $fees);

            return redirect()->route('client.invoices.show', $id)
                ->with('success', 'Payment completed successfully!');
        } catch (\Exception $e) {
            Log::error('Stripe success callback failed', ['invoice' => $id, 'session' => $sessionId, 'error' => $e->getMessage()]);
            return redirect()->route('client.invoices.show', $id)
                ->withErrors(['payment' => 'Payment verification failed. Please contact support if you were charged.']);
        }
    }

    /**
     * Mark a bank transfer as pending (record user's intent to pay).
     */
    public function bankTransferNotify(Request $request, int $id)
    {
        // Just redirect back with instructions acknowledged
        return back()->with('success', 'Bank transfer instructions noted. Your invoice will be updated once payment is received.');
    }
}
