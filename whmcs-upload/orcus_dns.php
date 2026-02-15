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

// Load WHMCS registrar helper functions first, then the registrar module
// This ensures injectDomainObjectIfNecessary() and other helpers are available
$registrarFunctionsFile = $whmcsDir . '/includes/registrarfunctions.php';
if (file_exists($registrarFunctionsFile)) {
    require_once $registrarFunctionsFile;
}

// Also load any common includes the registrar modules depend on
$moduleFunctions = $whmcsDir . '/includes/modulefunctions.php';
if (file_exists($moduleFunctions)) {
    require_once $moduleFunctions;
}

// Use WHMCS's built-in function to get registrar module params
// This properly loads the module and decrypts configs
if (function_exists('getregistrarconfigoptions')) {
    $registrarConfig = getregistrarconfigoptions($registrar);
} else {
    // Manual fallback
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
}

// Load the registrar module file
$modulePath = $whmcsDir . '/modules/registrars/' . $registrar . '/' . $registrar . '.php';
if (!file_exists($modulePath)) {
    echo json_encode(['result' => 'error', 'message' => "Registrar module file not found: {$registrar}"]);
    exit;
}
require_once $modulePath;

// ── Build registrar params ─────────────────────────────────
$parts = explode('.', $domain->domain, 2);

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

} elseif ($action === 'ListFunctions') {
    // Debug: list all registrar functions available
    $fns = get_defined_functions();
    $regFns = array_filter($fns['user'], function($f) use ($registrar) {
        return stripos($f, strtolower($registrar) . '_') === 0;
    });
    echo json_encode(['result' => 'success', 'registrar' => $registrar, 'functions' => array_values($regFns)]);

} elseif ($action === 'SaveDNS') {

    // Parse records from POST
    $rawRecords = $_POST['dnsrecords'] ?? $_REQUEST['dnsrecords'] ?? '';

    // If empty, try reading from raw input
    if (empty($rawRecords)) {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            parse_str($rawInput, $parsedInput);
            $rawRecords = $parsedInput['dnsrecords'] ?? '';
        }
    }

    // Try WHMCS's own request handler
    if (empty($rawRecords) && class_exists('App')) {
        try { $rawRecords = App::getFromRequest('dnsrecords'); } catch (\Exception $e) {}
    }

    if (empty($rawRecords)) {
        echo json_encode(['result' => 'error', 'message' => 'No DNS records provided']);
        exit;
    }

    // WHMCS sanitises POST values: adds backslashes (magic quotes) AND html-encodes them.
    if (is_string($rawRecords)) {
        $rawRecords = html_entity_decode(stripslashes($rawRecords), ENT_QUOTES, 'UTF-8');
    }

    $dnsRecords = is_string($rawRecords) ? json_decode($rawRecords, true) : $rawRecords;
    if (!is_array($dnsRecords) || empty($dnsRecords)) {
        echo json_encode([
            'result' => 'error',
            'message' => 'Failed to parse DNS records JSON',
            'debug_raw' => substr($rawRecords, 0, 500),
            'json_error' => json_last_error_msg(),
        ]);
        exit;
    }

    // Format desired records
    $desired = [];
    foreach ($dnsRecords as $rec) {
        $desired[] = [
            'hostname' => $rec['hostname'] ?? $rec['name'] ?? '',
            'type'     => strtoupper($rec['type'] ?? 'A'),
            'address'  => $rec['address'] ?? $rec['content'] ?? '',
            'priority' => (string)($rec['priority'] ?? ''),
        ];
    }

    // ── Strategy: First try the standard SaveDNS ──
    $fn = $registrar . '_SaveDNS';
    if (function_exists($fn)) {
        $params['dnsrecords'] = $desired;
        try {
            $result = call_user_func($fn, $params);
            if (empty($result['error'])) {
                echo json_encode(['result' => 'success', 'message' => 'DNS records saved successfully']);
                exit;
            }
        } catch (\Exception $e) {
            // SaveDNS failed — fall through to manual approach
        }
    }

    // ── Fallback: manual diff using GetDNS + individual Add/Delete ──
    $getDnsFn  = $registrar . '_GetDNS';
    if (!function_exists($getDnsFn)) {
        // No way to diff, return the SaveDNS error
        echo json_encode(['result' => 'error', 'message' => isset($result['error']) ? $result['error'] : 'SaveDNS not supported']);
        exit;
    }

    // Fetch existing records
    $existing = [];
    try {
        $getResult = call_user_func($getDnsFn, $params);
        if (is_array($getResult)) {
            foreach ($getResult as $r) {
                if (is_array($r) && isset($r['hostname'])) {
                    $existing[] = [
                        'hostname' => $r['hostname'],
                        'type'     => strtoupper($r['type'] ?? 'A'),
                        'address'  => $r['address'] ?? '',
                        'priority' => (string)($r['priority'] ?? ''),
                        'recid'    => $r['recid'] ?? null,
                    ];
                }
            }
        }
    } catch (\Exception $e) {
        echo json_encode(['result' => 'error', 'message' => 'Failed to fetch existing DNS records: ' . $e->getMessage()]);
        exit;
    }

    // Build lookup keys: hostname|type|address
    $existingKeys = [];
    foreach ($existing as $r) {
        $existingKeys[strtolower($r['hostname'] . '|' . $r['type'] . '|' . $r['address'])] = $r;
    }
    $desiredKeys = [];
    foreach ($desired as $r) {
        $desiredKeys[strtolower($r['hostname'] . '|' . $r['type'] . '|' . $r['address'])] = $r;
    }

    // Records to add: in desired but not in existing
    $toAdd = [];
    foreach ($desiredKeys as $key => $r) {
        if (!isset($existingKeys[$key])) {
            $toAdd[] = $r;
        }
    }

    // Records to delete: in existing but not in desired
    $toDelete = [];
    foreach ($existingKeys as $key => $r) {
        if (!isset($desiredKeys[$key])) {
            $toDelete[] = $r;
        }
    }

    $errors = [];

    // Delete records that are no longer desired
    foreach ($toDelete as $rec) {
        $params['dnsrecords'] = [$rec]; // just the one to delete
        // Try using dedicated delete function if available
        // WHMCS registrar modules often use SaveDNS with empty set logic
        // For now, we'll handle this after adds
    }

    // For the manual approach, we call SaveDNS with existing-that-stay + new records individually
    // Many registrar modules handle add vs update via the registrar API
    // The best universal approach: delete all existing, then add all desired
    // But that could cause downtime. Instead, let's add new records one at a time.

    // Try to add new records by calling SaveDNS with existing + one new record at a time
    $currentRecords = [];
    foreach ($existingKeys as $key => $r) {
        if (isset($desiredKeys[$key])) {
            $currentRecords[] = $r; // keep existing records that are still desired
        }
    }

    // Add new records one at a time
    foreach ($toAdd as $newRec) {
        $currentRecords[] = $newRec;
        $params['dnsrecords'] = $currentRecords;
        try {
            $result = call_user_func($fn, $params);
            if (!empty($result['error'])) {
                // Single record add failed — try without it
                array_pop($currentRecords);
                $errors[] = $newRec['type'] . '|' . $newRec['hostname'] . '|' . $newRec['address'] . ': ' . $result['error'];
            }
        } catch (\Exception $e) {
            array_pop($currentRecords);
            $errors[] = $newRec['type'] . '|' . $newRec['hostname'] . ': ' . $e->getMessage();
        }
    }

    // Now delete records that should be removed
    if (!empty($toDelete)) {
        // Remove deleted records: call SaveDNS with only the desired records
        $params['dnsrecords'] = $desired;
        try {
            $result = call_user_func($fn, $params);
            if (!empty($result['error'])) {
                $errors[] = 'Delete pass: ' . $result['error'];
            }
        } catch (\Exception $e) {
            $errors[] = 'Delete pass: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        echo json_encode(['result' => 'error', 'message' => implode('; ', $errors)]);
    } else {
        echo json_encode(['result' => 'success', 'message' => 'DNS records saved successfully']);
    }

} else {
    echo json_encode(['result' => 'error', 'message' => "Unknown action: {$action}. Use GetDNS or SaveDNS."]);
}
