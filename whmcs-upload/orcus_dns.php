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
        echo json_encode(['result' => 'error', 'message' => 'Failed to parse DNS records JSON']);
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

    // ──────────────────────────────────────────────────────────────
    // LogicBoxes HTTP API approach (Stargate = LogicBoxes platform)
    //
    // The stargate module's SaveDNS only MODIFIES existing records.
    // To ADD new records we must call the LogicBoxes API directly.
    // Strategy:
    //   1. Fetch existing records via GetDNS
    //   2. Diff: find records to ADD, MODIFY, DELETE
    //   3. Call the appropriate LogicBoxes endpoint for each
    // ──────────────────────────────────────────────────────────────

    $apiBaseUrl = 'https://httpapi.com/api/';
    $authUserId = $registrarConfig['ResellerID'] ?? '';
    $apiKey     = $registrarConfig['APIKey'] ?? '';

    // Helper: call LogicBoxes API
    $callApi = function($endpoint, $postData = []) use ($apiBaseUrl, $authUserId, $apiKey) {
        $postData['auth-userid'] = $authUserId;
        $postData['api-key']     = $apiKey;

        $url = $apiBaseUrl . $endpoint;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => "cURL error: $error"];
        }
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => "Invalid response (HTTP $httpCode): " . substr($response, 0, 300)];
        }
        return $decoded;
    };

    // Map record types to LogicBoxes API endpoint names
    $typeEndpoints = [
        'A'     => 'ipv4',
        'AAAA'  => 'ipv6',
        'CNAME' => 'cname',
        'MX'    => 'mx',
        'NS'    => 'ns',
        'TXT'   => 'txt',
        'SRV'   => 'srv',
    ];

    // ── Step 1: Get existing records via registrar module ──
    $existingRecords = [];
    $fn = $registrar . '_GetDNS';
    if (function_exists($fn)) {
        try {
            $getResult = call_user_func($fn, $params);
            if (is_array($getResult)) {
                foreach ($getResult as $r) {
                    if (is_array($r) && isset($r['hostname'])) {
                        $existingRecords[] = [
                            'hostname' => $r['hostname'],
                            'type'     => strtoupper($r['type'] ?? 'A'),
                            'address'  => $r['address'] ?? '',
                            'priority' => (string)($r['priority'] ?? ''),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // If GetDNS fails, treat as empty — we'll just add everything
        }
    }

    // ── Step 2: Diff — figure out adds, modifies, deletes ──
    // Key: "type|hostname" (e.g. "A|www" or "MX|@")
    $makeKey = function($rec) {
        return strtoupper($rec['type']) . '|' . strtolower($rec['hostname']);
    };

    $existingByKey = [];
    foreach ($existingRecords as $rec) {
        $key = $makeKey($rec);
        $existingByKey[$key] = $rec;
    }

    $desiredByKey = [];
    foreach ($desired as $rec) {
        $key = $makeKey($rec);
        $desiredByKey[$key] = $rec;
    }

    $toAdd    = []; // Records that don't exist yet
    $toModify = []; // Records that exist but have different address/priority
    $toDelete = []; // Records that exist but are not in desired list

    foreach ($desired as $rec) {
        $key = $makeKey($rec);
        if (!isset($existingByKey[$key])) {
            $toAdd[] = $rec;
        } else {
            // Exists — check if address or priority changed
            $ex = $existingByKey[$key];
            if ($ex['address'] !== $rec['address'] || $ex['priority'] !== $rec['priority']) {
                $toModify[] = ['old' => $ex, 'new' => $rec];
            }
            // else: identical, no action needed
        }
    }

    foreach ($existingRecords as $rec) {
        $key = $makeKey($rec);
        if (!isset($desiredByKey[$key])) {
            $toDelete[] = $rec;
        }
    }

    $errors   = [];
    $added    = 0;
    $modified = 0;
    $deleted  = 0;
    $domainName = $domain->domain;

    // Convert hostname: "@" means empty host for LogicBoxes, otherwise use as-is
    $hostForApi = function($hostname) {
        return ($hostname === '@' || $hostname === '') ? '' : $hostname;
    };

    // ── Step 3a: ADD new records via LogicBoxes API ──
    foreach ($toAdd as $rec) {
        $type = $rec['type'];
        $epName = $typeEndpoints[$type] ?? null;
        if (!$epName) {
            $errors[] = "Unsupported record type for add: {$type}";
            continue;
        }

        $postData = [
            'domain-name' => $domainName,
            'value'       => $rec['address'],
            'host'        => $hostForApi($rec['hostname']),
            'ttl'         => 14400,
        ];

        if ($type === 'MX' && !empty($rec['priority'])) {
            $postData['priority'] = (int)$rec['priority'];
        }

        $endpoint = "dns/manage/add-{$epName}-record.json";
        $result   = $callApi($endpoint, $postData);

        if (isset($result['status']) && strtolower($result['status']) === 'success') {
            $added++;
        } elseif (isset($result['status']) && stripos($result['status'], 'success') !== false) {
            $added++;
        } elseif (isset($result['error'])) {
            $errors[] = "Add {$type} {$rec['hostname']}: " . (is_array($result['error']) ? json_encode($result['error']) : $result['error']);
        } else {
            // Some endpoints return {"status":"Success"} or plain "Success"
            $added++;
        }
    }

    // ── Step 3b: MODIFY existing records via LogicBoxes API ──
    foreach ($toModify as $pair) {
        $old = $pair['old'];
        $rec = $pair['new'];
        $type = $rec['type'];
        $epName = $typeEndpoints[$type] ?? null;
        if (!$epName) {
            $errors[] = "Unsupported record type for modify: {$type}";
            continue;
        }

        $postData = [
            'domain-name'  => $domainName,
            'host'         => $hostForApi($rec['hostname']),
            'current-value' => $old['address'],
            'new-value'    => $rec['address'],
            'ttl'          => 14400,
        ];

        if ($type === 'MX') {
            if (!empty($rec['priority'])) {
                $postData['new-priority'] = (int)$rec['priority'];
            }
            if (!empty($old['priority'])) {
                $postData['current-priority'] = (int)$old['priority'];
            }
        }

        $endpoint = "dns/manage/modify-{$epName}-record.json";
        $result   = $callApi($endpoint, $postData);

        if (isset($result['status']) && stripos($result['status'], 'success') !== false) {
            $modified++;
        } elseif (isset($result['error'])) {
            $errors[] = "Modify {$type} {$rec['hostname']}: " . (is_array($result['error']) ? json_encode($result['error']) : $result['error']);
        } else {
            $modified++;
        }
    }

    // ── Step 3c: DELETE removed records via LogicBoxes API ──
    foreach ($toDelete as $rec) {
        $type = $rec['type'];
        $epName = $typeEndpoints[$type] ?? null;
        if (!$epName) {
            $errors[] = "Unsupported record type for delete: {$type}";
            continue;
        }

        $postData = [
            'domain-name' => $domainName,
            'host'        => $hostForApi($rec['hostname']),
            'value'       => $rec['address'],
        ];

        if ($type === 'MX' && !empty($rec['priority'])) {
            $postData['priority'] = (int)$rec['priority'];
        }

        $endpoint = "dns/manage/delete-{$epName}-record.json";
        $result   = $callApi($endpoint, $postData);

        if (isset($result['status']) && stripos($result['status'], 'success') !== false) {
            $deleted++;
        } elseif (isset($result['error'])) {
            // Ignore "does not exist" errors for delete
            $errMsg = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
            if (stripos($errMsg, 'does not exist') === false) {
                $errors[] = "Delete {$type} {$rec['hostname']}: {$errMsg}";
            } else {
                $deleted++;
            }
        } else {
            $deleted++;
        }
    }

    // ── Build response ──
    $unchanged = count($desired) - $added - $modified;
    if ($unchanged < 0) $unchanged = 0;

    if (empty($errors)) {
        echo json_encode([
            'result'    => 'success',
            'message'   => "DNS records saved. Added: {$added}, Modified: {$modified}, Deleted: {$deleted}, Unchanged: {$unchanged}",
            'added'     => $added,
            'modified'  => $modified,
            'deleted'   => $deleted,
            'unchanged' => $unchanged,
        ]);
    } else {
        // Partial success or full failure
        $anySuccess = ($added + $modified + $deleted) > 0;
        echo json_encode([
            'result'    => $anySuccess ? 'success' : 'error',
            'message'   => implode('; ', $errors) . ($anySuccess ? " (Added: {$added}, Modified: {$modified}, Deleted: {$deleted})" : ''),
            'added'     => $added,
            'modified'  => $modified,
            'deleted'   => $deleted,
            'errors'    => $errors,
        ]);
    }

} else {
    echo json_encode(['result' => 'error', 'message' => "Unknown action: {$action}. Use GetDNS or SaveDNS."]);
}
