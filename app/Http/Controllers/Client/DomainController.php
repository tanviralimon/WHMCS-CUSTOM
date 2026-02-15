<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DomainController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    // ─── My Domains (existing) ─────────────────────────────

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

        // Fetch all domain data in parallel-safe manner
        $ns   = $this->whmcs->domainGetNameservers($id);
        $lock = $this->whmcs->domainGetLockingStatus($id);

        // Get WHOIS contact info
        $whois = $this->whmcs->domainGetWhoisInfo($id);

        // Get DNS records via custom proxy
        $dns = $this->whmcs->domainGetDNS($id);
        $dnsRecords = [];
        if (($dns['result'] ?? '') === 'success' && !empty($dns['records'])) {
            $dnsRecords = $dns['records'];
        }

        // Get addons for this domain (from the domain data itself)
        $addons = [];
        foreach (['dnsmanagement', 'emailforwarding', 'idprotection'] as $addon) {
            if (isset($domain[$addon])) {
                $addons[$addon] = $domain[$addon] === 'on' || $domain[$addon] === '1' || $domain[$addon] === 1;
            }
        }

        // Auto-enable DNS management if not already active (it's free)
        if (empty($addons['dnsmanagement'])) {
            try {
                $this->whmcs->updateClientDomain($id, ['dnsmanagement' => true]);
                $addons['dnsmanagement'] = true;
            } catch (\Throwable) {
                // Silently continue — DNS tab will still work
                $addons['dnsmanagement'] = true;
            }
        }

        return Inertia::render('Client/Domains/Show', [
            'domain'      => $domain,
            'nameservers' => $ns,
            'lockStatus'  => $lock['lockstatus'] ?? null,
            'whois'       => $whois,
            'dnsRecords'  => $dnsRecords,
            'addons'      => $addons,
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

        // WHMCS DomainRequestEPP returns the EPP code in the 'eppcode' field
        $eppCode = $result['eppcode'] ?? null;

        if ($eppCode) {
            return back()->with('eppCode', $eppCode);
        }

        return back()->with('success', 'EPP/Authorization code request submitted. Check your email if not displayed.');
    }

    // ─── Toggle Auto Renew ─────────────────────────────────

    public function toggleAutoRenew(Request $request, int $id)
    {
        $request->validate(['autorenew' => 'required|boolean']);

        $result = $this->whmcs->updateClientDomain($id, [
            'autorecurring' => $request->boolean('autorenew') ? 1 : 0,
        ]);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to update auto-renew.']);
        }

        return back()->with('success', $request->boolean('autorenew') ? 'Auto-renewal enabled.' : 'Auto-renewal disabled.');
    }

    // ─── Update WHOIS Contact Info ─────────────────────────

    public function updateWhoisContact(Request $request, int $id)
    {
        $request->validate([
            'contactdetails' => 'required|array',
        ]);

        $result = $this->whmcs->domainUpdateWhoisInfo($id, $request->contactdetails);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to update contact information.']);
        }

        return back()->with('success', 'Contact information updated successfully.');
    }

    // ─── DNS Record Management ─────────────────────────────

    public function saveDnsRecords(Request $request, int $id)
    {
        $request->validate([
            'records'          => 'required|array|min:1',
            'records.*.name'   => 'required|string|max:255',
            'records.*.type'   => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA',
            'records.*.address' => 'required|string|max:255',
            'records.*.priority' => 'nullable|integer',
            'records.*.ttl'     => 'nullable|integer|min:60',
        ]);

        // Build DNS records for the proxy (hostname, type, address, priority)
        $dnsRecords = [];
        foreach ($request->records as $record) {
            $entry = [
                'hostname' => $record['name'],
                'type'     => $record['type'],
                'address'  => $record['address'],
            ];
            if (!empty($record['priority'])) {
                $entry['priority'] = (string) $record['priority'];
            } else {
                $entry['priority'] = '';
            }
            $dnsRecords[] = $entry;
        }

        \Illuminate\Support\Facades\Log::info('DNS Save Request', [
            'domain_id' => $id,
            'records_count' => count($dnsRecords),
            'records' => $dnsRecords,
        ]);

        try {
            $result = $this->whmcs->domainSetDNS($id, $dnsRecords);

            \Illuminate\Support\Facades\Log::info('DNS Save Result', ['result' => $result]);

            if (($result['result'] ?? '') !== 'success') {
                return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to save DNS records.']);
            }

            return back()->with('success', 'DNS records updated successfully.');
        } catch (\App\Exceptions\WhmcsApiException $e) {
            \Illuminate\Support\Facades\Log::error('DNS Save Exception', ['error' => $e->getMessage()]);
            return back()->withErrors(['whmcs' => $e->getMessage()]);
        }
    }

    // ─── Private Nameservers ───────────────────────────────

    public function registerPrivateNameserver(Request $request, int $id)
    {
        $request->validate([
            'nameserver' => 'required|string|max:255',
            'ipaddress'  => 'required|ip',
        ]);

        $result = $this->whmcs->domainRegisterNameserver($request->nameserver, $request->ipaddress);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to register nameserver.']);
        }

        return back()->with('success', 'Private nameserver registered successfully.');
    }

    public function modifyPrivateNameserver(Request $request, int $id)
    {
        $request->validate([
            'nameserver' => 'required|string|max:255',
            'currentip'  => 'required|ip',
            'newip'      => 'required|ip',
        ]);

        $result = $this->whmcs->domainModifyNameserver($request->nameserver, $request->currentip, $request->newip);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to modify nameserver.']);
        }

        return back()->with('success', 'Private nameserver IP updated successfully.');
    }

    public function deletePrivateNameserver(Request $request, int $id)
    {
        $request->validate([
            'nameserver' => 'required|string|max:255',
        ]);

        $result = $this->whmcs->domainDeleteNameserver($request->nameserver);

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['whmcs' => $result['message'] ?? 'Failed to delete nameserver.']);
        }

        return back()->with('success', 'Private nameserver deleted.');
    }

    // ─── Domain Search Page ────────────────────────────────

    public function searchDomain(Request $request)
    {
        $currencyId = (int) ($request->get('currency') ?: session('currency_id', 1));
        $currencies = $this->whmcs->getCurrencies();
        $currencyList = $currencies['currencies']['currency'] ?? [];

        // Get TLD pricing so the search page can show suggestions with pricing
        $tldResult = $this->whmcs->getTLDPricing($currencyId);
        $tlds = [];
        foreach ($tldResult['pricing'] ?? [] as $tld => $pricing) {
            $registerPrice = null;
            if (isset($pricing['register']) && is_array($pricing['register'])) {
                // Get the first (1-year) price
                foreach ($pricing['register'] as $years => $price) {
                    $registerPrice = $price;
                    break;
                }
            }
            $tlds[$tld] = [
                'tld'   => $tld,
                'register' => $registerPrice,
                'transfer' => isset($pricing['transfer']) ? (array_values((array) $pricing['transfer'])[0] ?? null) : null,
                'renew'    => isset($pricing['renew']) ? (array_values((array) $pricing['renew'])[0] ?? null) : null,
            ];
        }

        // Find active currency symbol
        $activeCurrency = collect($currencyList)->firstWhere('id', $currencyId) ?? ($currencyList[0] ?? null);

        return Inertia::render('Client/Domains/Search', [
            'query'          => $request->get('domain', ''),
            'result'         => null, // Will be populated via AJAX check
            'tlds'           => $tlds,
            'currencies'     => $currencyList,
            'activeCurrency' => $activeCurrency,
            'currencyId'     => $currencyId,
        ]);
    }

    // ─── AJAX: Check domain availability ───────────────────

    public function checkAvailability(Request $request)
    {
        $request->validate(['domain' => 'required|string|max:255']);

        $domain = trim($request->domain);

        // If no TLD, check popular ones
        if (!str_contains($domain, '.')) {
            $popularTlds = ['.com', '.net', '.org', '.io', '.dev', '.co', '.info', '.xyz', '.online', '.tech'];
            $results = [];
            foreach ($popularTlds as $tld) {
                $check = $this->whmcs->domainCheck($domain . $tld);
                $results[] = [
                    'domain' => $domain . $tld,
                    'status' => $check['status'] ?? 'unknown',
                ];
            }
            return response()->json(['results' => $results, 'multi' => true]);
        }

        // Single domain check
        $check = $this->whmcs->domainCheck($domain);
        return response()->json([
            'results' => [[
                'domain' => $domain,
                'status' => $check['status'] ?? 'unknown',
            ]],
            'multi' => false,
        ]);
    }

    // ─── Domain Pricing Page ───────────────────────────────

    public function pricing(Request $request)
    {
        $currencyId = (int) ($request->get('currency') ?: session('currency_id', 1));
        $currencies = $this->whmcs->getCurrencies();
        $currencyList = $currencies['currencies']['currency'] ?? [];

        $tldResult = $this->whmcs->getTLDPricing($currencyId);

        // Build structured pricing table
        $pricingTable = [];
        foreach ($tldResult['pricing'] ?? [] as $tld => $pricing) {
            $row = ['tld' => $tld];

            foreach (['register', 'transfer', 'renew'] as $type) {
                if (isset($pricing[$type]) && is_array($pricing[$type])) {
                    $row[$type] = [];
                    foreach ($pricing[$type] as $years => $price) {
                        $row[$type][$years] = $price;
                    }
                } else {
                    $row[$type] = null;
                }
            }

            $pricingTable[] = $row;
        }

        // Sort by TLD name
        usort($pricingTable, fn($a, $b) => strcmp($a['tld'], $b['tld']));

        $activeCurrency = collect($currencyList)->firstWhere('id', $currencyId) ?? ($currencyList[0] ?? null);

        return Inertia::render('Client/Domains/Pricing', [
            'pricing'        => $pricingTable,
            'currencies'     => $currencyList,
            'activeCurrency' => $activeCurrency,
            'currencyId'     => $currencyId,
        ]);
    }

    // ─── Add domain to cart ────────────────────────────────

    public function addToCart(Request $request)
    {
        $request->validate([
            'domain'  => 'required|string|max:255',
            'type'    => 'required|in:register,transfer',
            'years'   => 'required|integer|min:1|max:10',
            'price'   => 'required|string',
        ]);

        $cart = session('cart', ['items' => [], 'promo' => null]);
        $cart['items'][] = [
            'type'         => 'domain',
            'domain'       => $request->domain,
            'domaintype'   => $request->type,
            'regperiod'    => $request->years,
            'name'         => ($request->type === 'register' ? 'Register ' : 'Transfer ') . $request->domain,
            'price'        => $request->price,
            'billingcycle' => $request->years . ' Year(s)',
        ];
        session(['cart' => $cart]);

        return response()->json(['success' => true, 'cartCount' => count($cart['items'])]);
    }
}
