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

        // Get module info from SSO proxy (reads WHMCS server config directly)
        $serviceInfo = $this->whmcs->getServiceInfo($id, $clientId);

        $module = $serviceInfo['module'] ?? '';

        // Determine service type from module
        $hostingModules = ['spanel', 'cpanel', 'plesk', 'directadmin'];
        $vpsModules     = ['virtualizor', 'proxmox', 'solusvm'];

        $isHosting = in_array($module, $hostingModules);
        $isVps     = in_array($module, $vpsModules);

        // Fallback to group name if module not detected
        if (!$isHosting && !$isVps) {
            $groupName = strtolower($service['groupname'] ?? '');
            $isHosting = str_contains($groupName, 'hosting') || str_contains($groupName, 'web');
            $isVps     = str_contains($groupName, 'vps') || str_contains($groupName, 'virtual');
        }

        return Inertia::render('Client/Services/Show', [
            'service'         => $service,
            'serviceType'     => $isHosting ? 'hosting' : ($isVps ? 'vps' : 'other'),
            'serverModule'    => $module,
            'controlPanelUrl' => $serviceInfo['panelUrl'] ?? null,
            'webmailUrl'      => $serviceInfo['webmailUrl'] ?? null,
            'ssoSupported'    => $serviceInfo['ssoSupported'] ?? false,
        ]);
    }

    public function ssoLogin(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;
        $redirect = $request->query('redirect', ''); // SPanel deep link: "file/manager", "email/accounts", etc.

        try {
            // Call SSO proxy — goes directly to spanel/cpanel, NOT WHMCS
            $result = $this->whmcs->panelSsoLogin($id, $clientId, $redirect);

            if (($result['result'] ?? '') === 'success' && !empty($result['redirect_url'])) {
                return redirect()->away($result['redirect_url']);
            }

            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to create login session.']);
        } catch (\Exception $e) {
            return back()->withErrors(['whmcs' => 'SSO login failed: ' . $e->getMessage()]);
        }
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
