<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $status   = $request->get('status', '');
        $perPage  = 25;

        $result = $this->whmcs->getClientsProducts($clientId, ($page - 1) * $perPage, $perPage, $status ?: null);

        return Inertia::render('Client/Services/Index', [
            'services' => $result['products']['product'] ?? [],
            'total'    => (int) ($result['totalresults'] ?? 0),
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
            'filters'  => ['status' => $status],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;
        $result   = $this->whmcs->getClientProduct($clientId, $id);
        $service  = ($result['products']['product'] ?? [null])[0] ?? null;

        if (!$service) {
            abort(404);
        }

        return Inertia::render('Client/Services/Show', [
            'service' => $service,
        ]);
    }

    public function requestCancel(Request $request, int $id)
    {
        $request->validate([
            'type'   => 'required|in:Immediate,End of Billing Period',
            'reason' => 'nullable|string|max:1000',
        ]);

        $result = $this->whmcs->addCancelRequest($id, $request->type, $request->reason ?? '');

        return back()->with('success', 'Cancellation request submitted successfully.');
    }

    public function changePassword(Request $request, int $id)
    {
        $result = $this->whmcs->moduleChangePassword($id);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Password change failed.']);
        }

        return back()->with('success', 'Password has been changed successfully.');
    }

    public function moduleAction(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|string|in:reboot,shutdown,boot,resetpassword,console,vnc',
        ]);

        $result = $this->whmcs->moduleCustom($id, $request->action);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Action failed.']);
        }

        return back()->with('success', ucfirst($request->action) . ' action executed successfully.');
    }
}
