<?php

namespace App\Http\Controllers;

use App\Services\WhmcsApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    protected WhmcsApiService $whmcs;

    public function __construct(WhmcsApiService $whmcs)
    {
        $this->whmcs = $whmcs;
    }

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page = max(0, ((int) $request->get('page', 1)) - 1);
        $status = $request->get('status', '');

        $result = $this->whmcs->getInvoices($clientId, $status, $page * 25, 25);

        return Inertia::render('Invoices/Index', [
            'invoices' => $result['invoices']['invoice'] ?? [],
            'total' => (int) ($result['totalresults'] ?? 0),
            'page' => $page + 1,
            'status' => $status,
        ]);
    }

    public function show(int $id)
    {
        $result = $this->whmcs->getInvoice($id);

        if (($result['result'] ?? '') !== 'success') {
            abort(404);
        }

        return Inertia::render('Invoices/Show', [
            'invoice' => $result,
        ]);
    }
}
