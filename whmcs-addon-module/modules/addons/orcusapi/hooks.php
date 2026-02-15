<?php
/**
 * Orcus API – Hooks File
 *
 * Registers custom API actions that work through the standard
 * WHMCS API endpoint (/includes/api.php).
 *
 * Each custom action is prefixed with "Orcus" to avoid naming conflicts.
 *
 * @package OrcusAPI
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// ─── Helper: Load registrar module and build params ────────
function orcusapi_loadRegistrar($domainId)
{
    $domain = Capsule::table('tbldomains')->where('id', $domainId)->first();
    if (!$domain) {
        return ['error' => 'Domain not found'];
    }

    $registrar = $domain->registrar;
    if (empty($registrar)) {
        return ['error' => 'No registrar module assigned to this domain'];
    }

    // Load registrar module file
    $modulePath = ROOTDIR . '/modules/registrars/' . $registrar . '/' . $registrar . '.php';
    if (!file_exists($modulePath)) {
        return ['error' => "Registrar module file not found: {$registrar}"];
    }
    require_once $modulePath;

    // Split domain into SLD and TLD
    $domainName = $domain->domain;
    $parts = explode('.', $domainName, 2);

    // Load registrar configuration from WHMCS database
    $registrarConfig = [];
    $configRows = Capsule::table('tblregistrars')
        ->where('registrar', $registrar)
        ->get();

    foreach ($configRows as $row) {
        $value = $row->value;
        // Decrypt encrypted values
        if (!empty($value)) {
            try {
                $decrypted = localAPI('DecryptPassword', ['password2' => $value]);
                if (isset($decrypted['password'])) {
                    $value = $decrypted['password'];
                }
            } catch (\Exception $e) {
                // Use raw value if decryption fails
            }
        }
        $registrarConfig[$row->setting] = $value;
    }

    // Build params array that the registrar module expects
    $params = array_merge($registrarConfig, [
        'domainid'        => $domainId,
        'sld'             => $parts[0],
        'tld'             => $parts[1] ?? '',
        'domainname'      => $domainName,
        'domain'          => $domainName,
        'registrar'       => $registrar,
        'regperiod'       => $domain->registrationperiod ?? 1,
        'dnsmanagement'   => (bool) $domain->dnsmanagement,
        'emailforwarding' => (bool) $domain->emailforwarding,
        'idprotection'    => (bool) $domain->idprotection,
    ]);

    return [
        'params'    => $params,
        'registrar' => $registrar,
        'domain'    => $domain,
    ];
}

// ═══════════════════════════════════════════════════════════
// CUSTOM API ACTION: OrcusGetDNS
// ═══════════════════════════════════════════════════════════
add_hook('CustomApi', 1, function ($vars) {
    if ($vars['action'] !== 'OrcusGetDNS') {
        return;
    }

    $domainId = (int) ($_POST['domainid'] ?? $_GET['domainid'] ?? 0);
    if (!$domainId) {
        return ['result' => 'error', 'message' => 'domainid is required'];
    }

    $loaded = orcusapi_loadRegistrar($domainId);
    if (isset($loaded['error'])) {
        return ['result' => 'error', 'message' => $loaded['error']];
    }

    $functionName = $loaded['registrar'] . '_GetDNS';
    if (!function_exists($functionName)) {
        return [
            'result'  => 'error',
            'message' => "Registrar '{$loaded['registrar']}' does not support DNS management",
        ];
    }

    try {
        $result = call_user_func($functionName, $loaded['params']);

        if (isset($result['error'])) {
            return ['result' => 'error', 'message' => $result['error']];
        }

        return [
            'result'    => 'success',
            'records'   => is_array($result) ? array_values($result) : [],
            'domain'    => $loaded['domain']->domain,
            'registrar' => $loaded['registrar'],
        ];
    } catch (\Exception $e) {
        return ['result' => 'error', 'message' => 'GetDNS failed: ' . $e->getMessage()];
    }
});

// ═══════════════════════════════════════════════════════════
// CUSTOM API ACTION: OrcusSaveDNS
// ═══════════════════════════════════════════════════════════
add_hook('CustomApi', 1, function ($vars) {
    if ($vars['action'] !== 'OrcusSaveDNS') {
        return;
    }

    $domainId = (int) ($_POST['domainid'] ?? 0);
    if (!$domainId) {
        return ['result' => 'error', 'message' => 'domainid is required'];
    }

    $loaded = orcusapi_loadRegistrar($domainId);
    if (isset($loaded['error'])) {
        return ['result' => 'error', 'message' => $loaded['error']];
    }

    $functionName = $loaded['registrar'] . '_SaveDNS';
    if (!function_exists($functionName)) {
        return [
            'result'  => 'error',
            'message' => "Registrar '{$loaded['registrar']}' does not support DNS management",
        ];
    }

    // Parse DNS records from POST
    $dnsRecords = [];
    $rawRecords = $_POST['dnsrecords'] ?? '';
    if (is_string($rawRecords)) {
        $dnsRecords = json_decode($rawRecords, true) ?: [];
    } elseif (is_array($rawRecords)) {
        $dnsRecords = $rawRecords;
    }

    if (empty($dnsRecords)) {
        return ['result' => 'error', 'message' => 'No DNS records provided'];
    }

    // Format records as WHMCS expects
    $formattedRecords = [];
    foreach ($dnsRecords as $record) {
        $formattedRecords[] = [
            'hostname' => $record['hostname'] ?? $record['name'] ?? '',
            'type'     => strtoupper($record['type'] ?? 'A'),
            'address'  => $record['address'] ?? $record['content'] ?? $record['destination'] ?? '',
            'priority' => $record['priority'] ?? ($record['mxpref'] ?? ''),
        ];
    }

    $loaded['params']['dnsrecords'] = $formattedRecords;

    try {
        $result = call_user_func($functionName, $loaded['params']);

        if (isset($result['error'])) {
            return ['result' => 'error', 'message' => $result['error']];
        }

        return ['result' => 'success', 'message' => 'DNS records saved successfully'];
    } catch (\Exception $e) {
        return ['result' => 'error', 'message' => 'SaveDNS failed: ' . $e->getMessage()];
    }
});

// ═══════════════════════════════════════════════════════════
// CUSTOM API ACTION: OrcusGetStats
// Aggregated dashboard stats in a single API call (instead of 6+ calls)
// ═══════════════════════════════════════════════════════════
add_hook('CustomApi', 1, function ($vars) {
    if ($vars['action'] !== 'OrcusGetStats') {
        return;
    }

    $clientId = (int) ($_POST['clientid'] ?? 0);
    if (!$clientId) {
        return ['result' => 'error', 'message' => 'clientid is required'];
    }

    try {
        // Count active services
        $activeServices = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->count();

        // Count domains
        $totalDomains = Capsule::table('tbldomains')
            ->where('userid', $clientId)
            ->where('status', 'Active')
            ->count();

        // Count unpaid invoices + total due
        $unpaidInvoices = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Unpaid')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total_due')
            ->first();

        // Count open tickets
        $openTickets = Capsule::table('tbltickets')
            ->where('userid', $clientId)
            ->whereIn('status', ['Open', 'Answered', 'Customer-Reply', 'In Progress'])
            ->count();

        // Credit balance
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->select('credit', 'currency')
            ->first();

        // Bandwidth / disk usage from active services (if available)
        $services = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->select('id', 'domain', 'packageid', 'server', 'diskusage', 'disklimit', 'bwusage', 'bwlimit', 'lastupdate')
            ->limit(50)
            ->get();

        $usageSummary = [];
        foreach ($services as $svc) {
            if ($svc->diskusage > 0 || $svc->bwusage > 0) {
                $usageSummary[] = [
                    'serviceid' => $svc->id,
                    'domain'    => $svc->domain,
                    'disk'      => ['used' => $svc->diskusage, 'limit' => $svc->disklimit],
                    'bandwidth' => ['used' => $svc->bwusage, 'limit' => $svc->bwlimit],
                    'updated'   => $svc->lastupdate,
                ];
            }
        }

        // Upcoming renewals (next 30 days)
        $upcomingRenewals = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('domainstatus', 'Active')
            ->where('nextduedate', '<=', date('Y-m-d', strtotime('+30 days')))
            ->where('nextduedate', '>=', date('Y-m-d'))
            ->select('id', 'domain', 'nextduedate', 'amount', 'billingcycle')
            ->orderBy('nextduedate')
            ->limit(10)
            ->get();

        $domainRenewals = Capsule::table('tbldomains')
            ->where('userid', $clientId)
            ->where('status', 'Active')
            ->where('expirydate', '<=', date('Y-m-d', strtotime('+30 days')))
            ->where('expirydate', '>=', date('Y-m-d'))
            ->select('id', 'domain', 'expirydate', 'recurringamount')
            ->orderBy('expirydate')
            ->limit(10)
            ->get();

        return [
            'result' => 'success',
            'stats'  => [
                'activeServices'   => $activeServices,
                'totalDomains'     => $totalDomains,
                'unpaidInvoices'   => $unpaidInvoices->count ?? 0,
                'totalDue'         => number_format($unpaidInvoices->total_due ?? 0, 2, '.', ''),
                'openTickets'      => $openTickets,
                'creditBalance'    => $client->credit ?? '0.00',
                'currency'         => $client->currency ?? 1,
            ],
            'usage'            => $usageSummary,
            'upcomingRenewals' => [
                'services' => array_map(function ($r) { return (array) $r; }, $upcomingRenewals->toArray()),
                'domains'  => array_map(function ($r) { return (array) $r; }, $domainRenewals->toArray()),
            ],
        ];
    } catch (\Exception $e) {
        return ['result' => 'error', 'message' => 'Failed to get stats: ' . $e->getMessage()];
    }
});

// ═══════════════════════════════════════════════════════════
// CUSTOM API ACTION: OrcusGetServiceInfo
// Extended service info with server details, configoptions, custom fields
// ═══════════════════════════════════════════════════════════
add_hook('CustomApi', 1, function ($vars) {
    if ($vars['action'] !== 'OrcusGetServiceInfo') {
        return;
    }

    $serviceId = (int) ($_POST['serviceid'] ?? 0);
    if (!$serviceId) {
        return ['result' => 'error', 'message' => 'serviceid is required'];
    }

    try {
        // Get service details
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            return ['result' => 'error', 'message' => 'Service not found'];
        }

        // Get server info
        $server = null;
        if ($service->server) {
            $server = Capsule::table('tblservers')
                ->where('id', $service->server)
                ->select('id', 'name', 'hostname', 'ipaddress', 'type', 'noc', 'statusaddress')
                ->first();
        }

        // Get product info
        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->select('id', 'name', 'description', 'type', 'gid')
            ->first();

        // Get config options
        $configOptions = Capsule::table('tblhostingconfigoptions as hco')
            ->join('tblproductconfigoptions as pco', 'hco.configid', '=', 'pco.id')
            ->join('tblproductconfigoptionssub as pcosub', 'hco.optionid', '=', 'pcosub.id')
            ->where('hco.relid', $serviceId)
            ->select('pco.optionname', 'pcosub.optionname as value', 'hco.qty')
            ->get();

        // Get custom fields
        $customFields = Capsule::table('tblcustomfieldsvalues as cfv')
            ->join('tblcustomfields as cf', 'cfv.fieldid', '=', 'cf.id')
            ->where('cfv.relid', $serviceId)
            ->where('cf.type', 'product')
            ->select('cf.fieldname', 'cfv.value', 'cf.description')
            ->get();

        return [
            'result'        => 'success',
            'service'       => (array) $service,
            'server'        => $server ? (array) $server : null,
            'product'       => $product ? (array) $product : null,
            'configoptions' => array_map(function ($co) { return (array) $co; }, $configOptions->toArray()),
            'customfields'  => array_map(function ($cf) { return (array) $cf; }, $customFields->toArray()),
        ];
    } catch (\Exception $e) {
        return ['result' => 'error', 'message' => 'Failed to get service info: ' . $e->getMessage()];
    }
});

// ═══════════════════════════════════════════════════════════
// CUSTOM API ACTION: OrcusGetEmailForwarding
// ═══════════════════════════════════════════════════════════
add_hook('CustomApi', 1, function ($vars) {
    if ($vars['action'] !== 'OrcusGetEmailForwarding') {
        return;
    }

    $domainId = (int) ($_POST['domainid'] ?? 0);
    if (!$domainId) {
        return ['result' => 'error', 'message' => 'domainid is required'];
    }

    $loaded = orcusapi_loadRegistrar($domainId);
    if (isset($loaded['error'])) {
        return ['result' => 'error', 'message' => $loaded['error']];
    }

    $functionName = $loaded['registrar'] . '_GetEmailForwarding';
    if (!function_exists($functionName)) {
        return [
            'result'  => 'error',
            'message' => "Registrar '{$loaded['registrar']}' does not support email forwarding",
        ];
    }

    try {
        $result = call_user_func($functionName, $loaded['params']);

        if (isset($result['error'])) {
            return ['result' => 'error', 'message' => $result['error']];
        }

        return [
            'result'  => 'success',
            'rules'   => is_array($result) ? array_values($result) : [],
            'domain'  => $loaded['domain']->domain,
        ];
    } catch (\Exception $e) {
        return ['result' => 'error', 'message' => 'GetEmailForwarding failed: ' . $e->getMessage()];
    }
});

// ═══════════════════════════════════════════════════════════
// CUSTOM API ACTION: OrcusSaveEmailForwarding
// ═══════════════════════════════════════════════════════════
add_hook('CustomApi', 1, function ($vars) {
    if ($vars['action'] !== 'OrcusSaveEmailForwarding') {
        return;
    }

    $domainId = (int) ($_POST['domainid'] ?? 0);
    if (!$domainId) {
        return ['result' => 'error', 'message' => 'domainid is required'];
    }

    $loaded = orcusapi_loadRegistrar($domainId);
    if (isset($loaded['error'])) {
        return ['result' => 'error', 'message' => $loaded['error']];
    }

    $functionName = $loaded['registrar'] . '_SaveEmailForwarding';
    if (!function_exists($functionName)) {
        return [
            'result'  => 'error',
            'message' => "Registrar '{$loaded['registrar']}' does not support email forwarding",
        ];
    }

    // Parse forwarding rules from POST
    $rawRules = $_POST['forwarders'] ?? '';
    $forwarders = is_string($rawRules) ? (json_decode($rawRules, true) ?: []) : (is_array($rawRules) ? $rawRules : []);

    if (empty($forwarders)) {
        return ['result' => 'error', 'message' => 'No forwarding rules provided'];
    }

    $loaded['params']['prefix'] = array_column($forwarders, 'prefix');
    $loaded['params']['forwardto'] = array_column($forwarders, 'forwardto');

    try {
        $result = call_user_func($functionName, $loaded['params']);

        if (isset($result['error'])) {
            return ['result' => 'error', 'message' => $result['error']];
        }

        return ['result' => 'success', 'message' => 'Email forwarding rules saved successfully'];
    } catch (\Exception $e) {
        return ['result' => 'error', 'message' => 'SaveEmailForwarding failed: ' . $e->getMessage()];
    }
});
