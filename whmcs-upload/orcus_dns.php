<?php
/**
 * Orcus DNS Proxy — Upload to WHMCS root directory
 *
 * This file goes at: https://dash.orcustech.com/orcus_dns.php
 *
 * It bootstraps WHMCS and calls the registrar module's
 * GetDNS / SaveDNS functions directly.
 */

// ── Bootstrap WHMCS ────────────────────────────────────────
$whmcsDir = __DIR__;
if (!file_exists($whmcsDir . '/init.php')) {
    header('Content-Type: application/json');
    echo json_encode(['result' => 'error', 'message' => 'WHMCS init.php not found. Place this file in the WHMCS root directory.']);
    exit;
}

// Define WHMCS required constants
define('CLIENTAREA', true);

// Suppress output buffering issues
ob_start();
require_once $whmcsDir . '/init.php';
ob_end_clean();

// Now WHMCS is loaded — we have access to Capsule, localAPI, etc.
use WHMCS\Database\Capsule;

header('Content-Type: application/json');

// ── Authenticate ───────────────────────────────────────────
$identifier = $_POST['identifier'] ?? '';
$secret     = $_POST['secret'] ?? '';

if (empty($identifier) || empty($secret)) {
    echo json_encode(['result' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Validate API credentials against WHMCS database
// WHMCS stores API credentials in tblapi_roles (identifier + secret columns)
$validCred = false;
try {
    // Try the standard WHMCS API credentials table
    $tables = ['tblapi_roles', 'tblapicredentials', 'tblapi'];
    foreach ($tables as $table) {
        try {
            $cred = Capsule::table($table)
                ->where('identifier', $identifier)
                ->where('secret', $secret)
                ->first();
            if ($cred) {
                $validCred = true;
                break;
            }
        } catch (\Exception $e) {
            // Table doesn't exist, try next
            continue;
        }
    }

    // If no table matched, use a hardcoded secret as fallback
    // This is the API credential from the Laravel .env
    if (!$validCred) {
        $expectedId     = 'OvW1qayQgHu3mYa1UiqgCaOW0zrBKhQT';
        $expectedSecret = 'XHI9r0iN5zLMqIfd7AWUMsm4MKpymVxZ';
        if ($identifier === $expectedId && $secret === $expectedSecret) {
            $validCred = true;
        }
    }
} catch (\Exception $e) {
    // If all DB checks fail, fall back to hardcoded check
    $expectedId     = 'OvW1qayQgHu3mYa1UiqgCaOW0zrBKhQT';
    $expectedSecret = 'XHI9r0iN5zLMqIfd7AWUMsm4MKpymVxZ';
    if ($identifier === $expectedId && $secret === $expectedSecret) {
        $validCred = true;
    }
}

if (!$validCred) {
    echo json_encode(['result' => 'error', 'message' => 'Invalid API credentials']);
    exit;
}

// ── Route action ───────────────────────────────────────────
$action   = $_POST['action'] ?? '';
$domainId = (int) ($_POST['domainid'] ?? 0);

if (!$domainId) {
    echo json_encode(['result' => 'error', 'message' => 'domainid is required']);
    exit;
}

// ── Load domain & registrar ────────────────────────────────
$domain = Capsule::table('tbldomains')->where('id', $domainId)->first();
if (!$domain) {
    echo json_encode(['result' => 'error', 'message' => 'Domain not found']);
    exit;
}

$registrar = $domain->registrar;
if (empty($registrar)) {
    echo json_encode(['result' => 'error', 'message' => 'No registrar module assigned']);
    exit;
}

// Load the registrar module
$modulePath = $whmcsDir . '/modules/registrars/' . $registrar . '/' . $registrar . '.php';
if (!file_exists($modulePath)) {
    echo json_encode(['result' => 'error', 'message' => "Registrar module file not found: {$registrar}"]);
    exit;
}
require_once $modulePath;

// ── Build registrar params ─────────────────────────────────
$parts = explode('.', $domain->domain, 2);

// Load registrar config from DB and decrypt
$registrarConfig = [];
$configRows = Capsule::table('tblregistrars')->where('registrar', $registrar)->get();
foreach ($configRows as $row) {
    $value = $row->value;
    if (!empty($value)) {
        try {
            $decrypted = localAPI('DecryptPassword', ['password2' => $value]);
            if (!empty($decrypted['password'])) {
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
    'tld'        => isset($parts[1]) ? $parts[1] : '',
    'domainname' => $domain->domain,
    'domain'     => $domain->domain,
    'registrar'  => $registrar,
]);

// ── Handle actions ─────────────────────────────────────────

if ($action === 'GetDNS') {

    $fn = $registrar . '_GetDNS';
    if (!function_exists($fn)) {
        echo json_encode(['result' => 'error', 'message' => "Registrar '{$registrar}' does not support DNS management"]);
        exit;
    }

    try {
        $result = call_user_func($fn, $params);

        if (isset($result['error']) && !empty($result['error'])) {
            echo json_encode(['result' => 'error', 'message' => $result['error']]);
            exit;
        }

        // Registrar returns array of records: [{hostname, type, address, priority}]
        $records = [];
        if (is_array($result)) {
            foreach ($result as $r) {
                if (is_array($r) && isset($r['hostname'])) {
                    $records[] = $r;
                }
            }
        }

        echo json_encode([
            'result'    => 'success',
            'records'   => $records,
            'domain'    => $domain->domain,
            'registrar' => $registrar,
        ]);
    } catch (\Exception $e) {
        echo json_encode(['result' => 'error', 'message' => 'GetDNS error: ' . $e->getMessage()]);
    }

} elseif ($action === 'SaveDNS') {

    $fn = $registrar . '_SaveDNS';
    if (!function_exists($fn)) {
        echo json_encode(['result' => 'error', 'message' => "Registrar '{$registrar}' does not support DNS management"]);
        exit;
    }

    // Parse records from POST
    $rawRecords = $_POST['dnsrecords'] ?? '';
    $dnsRecords = is_string($rawRecords) ? json_decode($rawRecords, true) : $rawRecords;
    if (!is_array($dnsRecords) || empty($dnsRecords)) {
        echo json_encode(['result' => 'error', 'message' => 'No DNS records provided']);
        exit;
    }

    // Format records
    $formatted = [];
    foreach ($dnsRecords as $rec) {
        $formatted[] = [
            'hostname' => $rec['hostname'] ?? $rec['name'] ?? '',
            'type'     => strtoupper($rec['type'] ?? 'A'),
            'address'  => $rec['address'] ?? $rec['content'] ?? '',
            'priority' => $rec['priority'] ?? '',
        ];
    }
    $params['dnsrecords'] = $formatted;

    try {
        $result = call_user_func($fn, $params);

        if (isset($result['error']) && !empty($result['error'])) {
            echo json_encode(['result' => 'error', 'message' => $result['error']]);
            exit;
        }

        echo json_encode(['result' => 'success', 'message' => 'DNS records saved successfully']);
    } catch (\Exception $e) {
        echo json_encode(['result' => 'error', 'message' => 'SaveDNS error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['result' => 'error', 'message' => "Unknown action: {$action}. Use GetDNS or SaveDNS."]);
}
