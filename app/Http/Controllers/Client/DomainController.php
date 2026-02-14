<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $status   = $request->get('status', '');
        $perPage  = 25;

        $result = $this->whmcs->getClientsDomains($clientId, ($page - 1) * $perPage, $perPage, $status ?: null);

        return Inertia::render('Client/Domains/Index', [
            'domains' => $result['domains']['domain'] ?? [],
            'total'   => (int) ($result['totalresults'] ?? 0),
            'page'    => $page,
            'perPage' => $perPage,
            'status'  => $status,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $clientId = $request->user()->whmcs_client_id;
        $result   = $this->whmcs->getClientDomain($clientId, $id);
        $domain   = ($result['domains']['domain'] ?? [null])[0] ?? null;

        if (!$domain) {
            abort(404);
        }

        // Get nameservers
        $ns = $this->whmcs->domainGetNameservers($id);
        // Get lock status
        $lock = $this->whmcs->domainGetLockingStatus($id);

        return Inertia::render('Client/Domains/Show', [
            'domain'      => $domain,
            'nameservers' => $ns,
            'lockStatus'  => $lock['lockstatus'] ?? null,
        ]);
    }

    public function renew(Request $request, int $id)
    {
        $result = $this->whmcs->domainRenew($id);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Domain renewal failed.']);
        }

        return back()->with('success', 'Domain renewal initiated successfully.');
    }

    public function updateNameservers(Request $request, int $id)
    {
        $request->validate([
            'nameservers'   => 'required|array|min:1|max:5',
            'nameservers.*' => 'required|string|max:255',
        ]);

        $result = $this->whmcs->domainUpdateNameservers($id, $request->nameservers);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to update nameservers.']);
        }

        return back()->with('success', 'Nameservers updated successfully.');
    }

    public function toggleLock(Request $request, int $id)
    {
        $request->validate(['lock' => 'required|boolean']);

        $result = $this->whmcs->domainUpdateLockingStatus($id, $request->boolean('lock'));

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to update lock status.']);
        }

        return back()->with('success', $request->boolean('lock') ? 'Domain locked.' : 'Domain unlocked.');
    }

    public function requestEpp(Request $request, int $id)
    {
        $result = $this->whmcs->domainGetEPPCode($id);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to retrieve EPP code.']);
        }

        return back()->with('success', 'EPP/Authorization code has been sent to your email.');
    }

    public function searchDomain(Request $request)
    {
        $request->validate(['domain' => 'required|string|max:255']);

        $result = $this->whmcs->domainCheck($request->domain);

        return Inertia::render('Client/Domains/Search', [
            'query'  => $request->domain,
            'result' => $result,
        ]);
    }
}
