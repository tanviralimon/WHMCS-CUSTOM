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
        $status   = $request->get('status', 'Active');

        // Fetch ALL services from WHMCS (no status filter — WHMCS API filtering is unreliable)
        $result = $this->whmcs->getClientsProducts($clientId, 0, 250, null);

        $allServices = $result['products']['product'] ?? [];

        // Normalize: WHMCS returns object (not array) when there's only 1 result
        if (!empty($allServices) && !isset($allServices[0])) {
            $allServices = [$allServices];
        }

        // Sort: Active first, then Pending → Suspended → Terminated → Cancelled
        $statusOrder = ['Active' => 0, 'Pending' => 1, 'Suspended' => 2, 'Terminated' => 3, 'Cancelled' => 4, 'Fraud' => 5];
        usort($allServices, function ($a, $b) use ($statusOrder) {
            $aOrder = $statusOrder[$a['status'] ?? ''] ?? 9;
            $bOrder = $statusOrder[$b['status'] ?? ''] ?? 9;
            return $aOrder - $bOrder;
        });

        // Apply filter (unless "All")
        if (strtolower($status) !== 'all' && $status !== '') {
            $allServices = array_values(array_filter($allServices, function ($s) use ($status) {
                return strcasecmp($s['status'] ?? '', $status) === 0;
            }));
        }

        // Manual pagination
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 25;
        $total   = count($allServices);
        $services = array_slice($allServices, ($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Services/Index', [
            'services' => $services,
            'total'    => $total,
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
