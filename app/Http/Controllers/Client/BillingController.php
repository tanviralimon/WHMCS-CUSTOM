<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function transactions(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $perPage  = 25;

        $result = $this->whmcs->getTransactions($clientId, ($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Billing/Transactions', [
            'transactions' => $result['transactions']['transaction'] ?? [],
            'total'        => (int) ($result['totalresults'] ?? 0),
            'page'         => $page,
            'perPage'      => $perPage,
        ]);
    }

    public function credit(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $profile  = $this->whmcs->getClientsDetails($clientId);

        // Get credit log/history
        $credits = $this->whmcs->getCredits($clientId);

        // Get available payment methods for add funds
        $paymentMethods = $this->whmcs->getPaymentMethods();

        return Inertia::render('Client/Billing/Credit', [
            'credit'         => $profile['credit'] ?? '0.00',
            'currency'       => $profile['currency_code'] ?? 'USD',
            'credits'        => $credits['credits']['credit'] ?? [],
            'paymentMethods' => $paymentMethods['paymentmethods']['paymentmethod'] ?? [],
        ]);
    }

    public function addFunds(Request $request)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:1|max:10000',
            'payment_method' => 'required|string',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        try {
            $result = $this->whmcs->addCredit($clientId, (float) $request->amount, $request->payment_method);

            if (($result['result'] ?? '') === 'success' && !empty($result['invoiceid'])) {
                return redirect()->route('client.invoices.show', $result['invoiceid'])
                    ->with('success', 'Invoice created for your funds. Please complete the payment.');
            }

            return back()->with('success', 'Funds added successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => 'Failed to create invoice. Please try again.']);
        }
    }

    public function quotes(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $perPage  = 25;

        $result = $this->whmcs->getQuotes($clientId, ($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Billing/Quotes', [
            'quotes'  => $result['quotes']['quote'] ?? [],
            'total'   => (int) ($result['totalresults'] ?? 0),
            'page'    => $page,
            'perPage' => $perPage,
        ]);
    }

    public function showQuote(Request $request, int $id)
    {
        $result = $this->whmcs->getQuote($id);
        $quote  = ($result['quotes']['quote'] ?? [null])[0] ?? null;

        if (!$quote) {
            abort(404);
        }

        return Inertia::render('Client/Billing/QuoteShow', [
            'quote' => $quote,
        ]);
    }

    public function acceptQuote(Request $request, int $id)
    {
        $this->whmcs->acceptQuote($id);
        return back()->with('success', 'Quote accepted successfully.');
    }
}
