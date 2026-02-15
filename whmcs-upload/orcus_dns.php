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

} elseif ($action === 'DebugSaveDNS') {
    // Debug: discover the correct LogicBoxes/Stargate API base URL
    $info = [
        'registrar' => $registrar,
        'config_keys' => array_keys($registrarConfig),
        'ResellerID' => $registrarConfig['ResellerID'] ?? 'NOT SET',
        'APIKey' => substr($registrarConfig['APIKey'] ?? '', 0, 8) . '...',
        'TestMode' => $registrarConfig['TestMode'] ?? 'NOT SET',
        'Username' => $registrarConfig['Username'] ?? 'NOT SET',
    ];
    
    // Stargate/UK2 uses LogicBoxes platform
    // Try known LogicBoxes API base URLs to find which one works
    $testMode = !empty($registrarConfig['TestMode']) && $registrarConfig['TestMode'] !== 'off' && $registrarConfig['TestMode'] !== '';
    $apiUrls = $testMode ? [
        'https://test.httpapi.com/api/',
        'https://test.stargate.biz/api/',
    ] : [
        'https://httpapi.com/api/',
        'https://domaincheck.httpapi.com/api/',
        'https://stargate.biz/api/',
        'https://api.stargate.biz/api/',
    ];
    
    $authUserId = $registrarConfig['ResellerID'] ?? '';
    $apiKey = $registrarConfig['APIKey'] ?? '';
    
    $info['test_mode'] = $testMode;
    $info['api_tests'] = [];
    
    foreach ($apiUrls as $baseUrl) {
        $testUrl = $baseUrl . 'dns/manage/search-records.json?auth-userid=' . urlencode($authUserId) 
            . '&api-key=' . urlencode($apiKey) 
            . '&domain-name=' . urlencode($domain->domain) 
            . '&type=A&no-of-records=1&page-no=1';
        
        $ch = curl_init($testUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $info['api_tests'][] = [
            'url' => $baseUrl,
            'http_code' => $httpCode,
            'response' => $response ? substr($response, 0, 500) : null,
            'curl_error' => $error ?: null,
        ];
    }
    
    echo json_encode(['result' => 'success'] + $info);

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

    // ── Populate $_POST exactly like WHMCS admin DNS page (clientsdomaindns.php) ──
    $_POST['sub'] = 'save';
    $_POST['dnsrecordhost'] = [];
    $_POST['dnsrecordtype'] = [];
    $_POST['dnsrecordaddress'] = [];
    $_POST['dnsrecordpriority'] = [];
    foreach ($desired as $i => $rec) {
        $_POST['dnsrecordhost'][$i] = $rec['hostname'];
        $_POST['dnsrecordtype'][$i] = $rec['type'];
        $_POST['dnsrecordaddress'][$i] = $rec['address'];
        $_POST['dnsrecordpriority'][$i] = $rec['priority'];
    }
    // Mirror to $_REQUEST
    $_REQUEST = array_merge($_REQUEST, $_POST);

    $errors = [];
    $success = false;

    // ── Try WHMCS\Module\Registrar class (the proper internal way) ──
    if (class_exists('WHMCS\\Module\\Registrar')) {
        try {
            $regModule = new \WHMCS\Module\Registrar();
            $regModule->load($registrar);

            // Try calling saveDNS through the module class
            if (method_exists($regModule, 'call')) {
                // Build a domain model params array
                $moduleParams = $regModule->getParams($domain);
                if (!is_array($moduleParams)) {
                    $moduleParams = $params;
                }
                $moduleParams['dnsrecords'] = $desired;
                $modResult = $regModule->call('SaveDNS', $moduleParams);
                if ($modResult === true || (is_array($modResult) && empty($modResult['error']))) {
                    $success = true;
                } elseif (is_array($modResult) && !empty($modResult['error'])) {
                    $errors[] = 'Module: ' . $modResult['error'];
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Module exception: ' . $e->getMessage();
        }
    }

    // ── Fallback: Direct registrar function call with $params['dnsrecords'] ──
    if (!$success) {
        $fn = $registrar . '_SaveDNS';
        if (function_exists($fn)) {
            $params['dnsrecords'] = $desired;
            $errors = []; // reset
            try {
                $result = call_user_func($fn, $params);
                if (is_array($result) && empty($result['error'])) {
                    $success = true;
                } elseif (is_array($result) && !empty($result['error'])) {
                    $errors[] = $result['error'];
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if ($success) {
        echo json_encode(['result' => 'success', 'message' => 'DNS records saved successfully']);
    } else {
        echo json_encode(['result' => 'error', 'message' => implode('; ', $errors ?: ['Unknown error'])]);
    }

} else {
    echo json_encode(['result' => 'error', 'message' => "Unknown action: {$action}. Use GetDNS or SaveDNS."]);
}
