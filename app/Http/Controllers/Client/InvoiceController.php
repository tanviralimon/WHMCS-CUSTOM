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

        // Build WHMCS pay URL
        $payUrl = rtrim(config('whmcs.base_url'), '/') . '/viewinvoice.php?id=' . $id;

        return Inertia::render('Client/Invoices/Show', [
            'invoice' => $result,
            'payUrl'  => $payUrl,
        ]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        // Redirect to WHMCS invoice PDF
        $url = rtrim(config('whmcs.base_url'), '/') . '/dl.php?type=i&id=' . $id;
        return redirect()->away($url);
    }
}
