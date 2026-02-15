<?php
/**
 * Custom API Action: OrcusGetDNS
 *
 * Place this file in your WHMCS installation at:
 *   /includes/api/OrcusGetDNS.php
 *
 * Usage via API:
 *   POST /includes/api.php
 *   action=OrcusGetDNS&domainid=123&identifier=XXX&secret=XXX&responsetype=json
 *
 * Returns DNS records for a domain via the registrar module.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Validate required parameter
$domainId = (int) App::getFromRequest('domainid');
if (!$domainId) {
    $apiresults = ['result' => 'error', 'message' => 'domainid is required'];
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

    $functionName = $registrar . '_GetDNS';
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

    $params = array_merge($registrarConfig, [
        'domainid'   => $domainId,
        'sld'        => $parts[0],
        'tld'        => $parts[1] ?? '',
        'domainname' => $domain->domain,
        'domain'     => $domain->domain,
        'registrar'  => $registrar,
    ]);

    // Call registrar GetDNS
    $result = call_user_func($functionName, $params);

    if (isset($result['error']) && !empty($result['error'])) {
        $apiresults = ['result' => 'error', 'message' => $result['error']];
        return;
    }

    $apiresults = [
        'result'    => 'success',
        'records'   => is_array($result) ? array_values($result) : [],
        'domain'    => $domain->domain,
        'registrar' => $registrar,
    ];

} catch (\Exception $e) {
    $apiresults = ['result' => 'error', 'message' => 'GetDNS failed: ' . $e->getMessage()];
}
