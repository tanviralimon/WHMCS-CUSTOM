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

        // Fetch VPS stats if applicable
        $vpsStats = null;
        $vpsStatsError = null;
        if ($isVps && $service['status'] === 'Active') {
            $statsResult = $this->whmcs->getVpsStats($id, $clientId);
            if (($statsResult['result'] ?? '') === 'success' && !empty($statsResult['vps'])) {
                $vpsStats = $statsResult['vps'];
            } else {
                $vpsStatsError = $statsResult['message'] ?? 'Failed to load VPS stats';
                if (!empty($statsResult['debug'])) {
                    \Log::warning('VPS stats failed', ['service' => $id, 'result' => $statsResult]);
                }
            }
        }

        return Inertia::render('Client/Services/Show', [
            'service'         => $service,
            'serviceType'     => $isHosting ? 'hosting' : ($isVps ? 'vps' : 'other'),
            'serverModule'    => $module,
            'controlPanelUrl' => $serviceInfo['panelUrl'] ?? null,
            'webmailUrl'      => $serviceInfo['webmailUrl'] ?? null,
            'ssoSupported'    => $serviceInfo['ssoSupported'] ?? false,
            'vpsStats'        => $vpsStats,
            'vpsStatsError'   => $vpsStatsError,
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
        $clientId = $request->user()->whmcs_client_id;

        try {
            // Detect if this is a VPS — use Virtualizor Manage VPS API via SSO proxy
            $serviceInfo = $this->whmcs->getServiceInfo($id, $clientId);
            $module      = strtolower($serviceInfo['module'] ?? '');
            $vpsModules  = ['virtualizor', 'proxmox', 'solusvm'];

            if (in_array($module, $vpsModules)) {
                $result = $this->whmcs->vpsAction($id, $clientId, 'resetpassword');

                if (($result['result'] ?? '') !== 'success') {
                    return back()->withErrors(['whmcs' => $result['message'] ?? 'Password change failed.']);
                }

                // Return new password so frontend can display it
                $newPass = $result['new_password'] ?? null;
                return back()->with('success', $result['message'] ?? 'Password has been changed successfully.')
                             ->with('new_password', $newPass);
            }

            // Non-VPS: use standard WHMCS ModuleChangePassword
            $result = $this->whmcs->moduleChangePassword($id);

            if (($result['result'] ?? '') !== 'success') {
                return back()->withErrors(['whmcs' => $result['message'] ?? 'Password change failed.']);
            }

            return back()->with('success', 'Password has been changed successfully.');
        } catch (\Exception $e) {
            \Log::error('Change password failed', ['service' => $id, 'error' => $e->getMessage()]);
            return back()->withErrors(['whmcs' => 'Password change failed: ' . $e->getMessage()]);
        }
    }

    public function moduleAction(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|string|in:reboot,shutdown,boot,console,vnc',
        ]);

        $clientId = $request->user()->whmcs_client_id;
        $action   = $request->action;

        try {
            // Detect if this is a VPS service — route through SSO proxy instead of ModuleCustom
            $serviceInfo = $this->whmcs->getServiceInfo($id, $clientId);
            $module      = strtolower($serviceInfo['module'] ?? '');
            $vpsModules  = ['virtualizor', 'proxmox', 'solusvm'];

            if (in_array($module, $vpsModules)) {
                // VPS: use SSO proxy which calls Virtualizor/Proxmox API directly
                $result = $this->whmcs->vpsAction($id, $clientId, $action);

                if (($result['result'] ?? '') !== 'success') {
                    $errorMsg = $result['message'] ?? 'Action failed.';
                    // Include debug info if available (response keys from Virtualizor API)
                    if (!empty($result['debug'])) {
                        $debugStr = is_array($result['debug']) ? implode(', ', $result['debug']) : $result['debug'];
                        \Log::warning('VPS action failed', ['action' => $action, 'result' => $result]);
                        $errorMsg .= ' (Debug: ' . $debugStr . ')';
                    }
                    return back()->withErrors(['whmcs' => $errorMsg]);
                }

                // VNC/Console returns a redirect_url — pass it to frontend to open
                if (($action === 'vnc' || $action === 'console') && !empty($result['redirect_url'])) {
                    // Check if SSO actually worked (not just a fallback login URL)
                    $ssoType = $result['sso_type'] ?? '';
                    $msg = ($ssoType === 'direct_url')
                        ? 'Opening Virtualizor panel (login may be required).'
                        : 'VNC console opened.';
                    return back()->with('success', $msg)
                                 ->with('redirect_url', $result['redirect_url']);
                }

                // Password reset returns new password — pass to frontend for display
                if ($action === 'resetpassword' && !empty($result['new_password'])) {
                    return back()->with('success', $result['message'] ?? 'Password changed successfully.')
                                 ->with('new_password', $result['new_password']);
                }

                return back()->with('success', $result['message'] ?? ucfirst($action) . ' action executed successfully.');
            }

            // Non-VPS: use standard WHMCS ModuleCustom API
            $result = $this->whmcs->moduleCustom($id, $action);

            if (($result['result'] ?? '') !== 'success') {
                return back()->withErrors(['whmcs' => $result['message'] ?? 'Action failed.']);
            }

            return back()->with('success', ucfirst($action) . ' action executed successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['whmcs' => 'Action failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get available OS templates for VPS rebuild.
     */
    public function getOsTemplates(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;

        try {
            $result = $this->whmcs->getOsTemplates($id, $clientId);

            if (($result['result'] ?? '') !== 'success') {
                return response()->json([
                    'error' => $result['message'] ?? 'Failed to load OS templates',
                ], 422);
            }

            return response()->json([
                'templates' => $result['templates'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load OS templates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rebuild (reinstall OS) a VPS.
     */
    public function rebuildVps(Request $request, int $id)
    {
        $request->validate([
            'osid'    => 'required|integer|min:1',
            'newpass' => 'required|string|min:6|max:64',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        try {
            $result = $this->whmcs->rebuildVps(
                $id,
                $clientId,
                (int) $request->osid,
                $request->newpass
            );

            if (($result['result'] ?? '') !== 'success') {
                return back()->withErrors(['whmcs' => $result['message'] ?? 'Rebuild failed.']);
            }

            return back()->with('success', $result['message'] ?? 'VPS rebuild initiated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['whmcs' => 'Rebuild failed: ' . $e->getMessage()]);
        }
    }
}
