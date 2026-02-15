<?php
/**
 * Custom API Action: OrcusSaveDNS
 *
 * Place this file in your WHMCS installation at:
 *   /includes/api/OrcusSaveDNS.php
 *
 * Usage via API:
 *   POST /includes/api.php
 *   action=OrcusSaveDNS&domainid=123&dnsrecords=[...]&identifier=XXX&secret=XXX&responsetype=json
 *
 * Saves DNS records for a domain via the registrar module.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Validate required parameters
$domainId = (int) App::getFromRequest('domainid');
if (!$domainId) {
    $apiresults = ['result' => 'error', 'message' => 'domainid is required'];
    return;
}

$rawRecords = App::getFromRequest('dnsrecords');
if (empty($rawRecords)) {
    $apiresults = ['result' => 'error', 'message' => 'dnsrecords is required'];
    return;
}

// Parse DNS records (accept JSON string or array)
$dnsRecords = is_string($rawRecords) ? json_decode($rawRecords, true) : $rawRecords;
if (!is_array($dnsRecords) || empty($dnsRecords)) {
    $apiresults = ['result' => 'error', 'message' => 'Invalid dnsrecords format'];
    return;
}

try {
    // Get domain from database
    $domain = Capsule::table('tbldomains')->where('id', $domainId)->first();
    if (!$domain) {
        $apiresults = ['result' => 'error', 'message' => 'Domain not found'];
        return;
    }

    $registrar = $domain->registrar;
    if (empty($registrar)) {
        $apiresults = ['result' => 'error', 'message' => 'No registrar module assigned to this domain'];
        return;
    }

    // Load registrar module
    $modulePath = ROOTDIR . '/modules/registrars/' . $registrar . '/' . $registrar . '.php';
    if (!file_exists($modulePath)) {
        $apiresults = ['result' => 'error', 'message' => "Registrar module not found: {$registrar}"];
        return;
    }
    require_once $modulePath;

    $functionName = $registrar . '_SaveDNS';
    if (!function_exists($functionName)) {
        $apiresults = ['result' => 'error', 'message' => "Registrar '{$registrar}' does not support DNS management"];
        return;
    }

    // Build params for registrar module
    $parts = explode('.', $domain->domain, 2);
    $registrarConfig = [];
    $configRows = Capsule::table('tblregistrars')->where('registrar', $registrar)->get();
    foreach ($configRows as $row) {
        $value = $row->value;
        if (!empty($value)) {
            try {
                $decrypted = localAPI('DecryptPassword', ['password2' => $value]);
                if (isset($decrypted['password'])) {
                    $value = $decrypted['password'];
                }
            } catch (\Exception $e) {
                // Use raw value
            }
        }
        $registrarConfig[$row->setting] = $value;
    }

    // Format DNS records as WHMCS expects
    $formattedRecords = [];
    foreach ($dnsRecords as $record) {
        $formattedRecords[] = [
            'hostname' => $record['hostname'] ?? $record['name'] ?? '',
            'type'     => strtoupper($record['type'] ?? 'A'),
            'address'  => $record['address'] ?? $record['content'] ?? $record['destination'] ?? '',
            'priority' => $record['priority'] ?? ($record['mxpref'] ?? ''),
        ];
    }

    $params = array_merge($registrarConfig, [
        'domainid'   => $domainId,
        'sld'        => $parts[0],
        'tld'        => $parts[1] ?? '',
        'domainname' => $domain->domain,
        'domain'     => $domain->domain,
        'registrar'  => $registrar,
        'dnsrecords' => $formattedRecords,
    ]);

    // Call registrar SaveDNS
    $result = call_user_func($functionName, $params);

    if (isset($result['error']) && !empty($result['error'])) {
        $apiresults = ['result' => 'error', 'message' => $result['error']];
        return;
    }

    $apiresults = ['result' => 'success', 'message' => 'DNS records saved successfully'];

} catch (\Exception $e) {
    $apiresults = ['result' => 'error', 'message' => 'SaveDNS failed: ' . $e->getMessage()];
}
