<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    /**
     * Switch the active currency. Stores in session.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'currency_id' => 'required|integer|min:1',
        ]);

        // Verify the currency exists in WHMCS
        $currencies = $this->whmcs->getCurrencies();
        $currencyList = $currencies['currencies']['currency'] ?? [];
        $valid = collect($currencyList)->contains('id', $request->currency_id);

        if (!$valid) {
            return back()->withErrors(['currency' => 'Invalid currency selected.']);
        }

        session(['currency_id' => (int) $request->currency_id]);

        return back()->with('success', 'Currency updated.');
    }

    /**
     * Get available currencies (JSON for AJAX).
     */
    public function list()
    {
        $currencies = $this->whmcs->getCurrencies();
        return response()->json($currencies['currencies']['currency'] ?? []);
    }
}
