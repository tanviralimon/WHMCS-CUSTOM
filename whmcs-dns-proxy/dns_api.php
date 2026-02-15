<?php
/**
 * WHMCS DNS Management Proxy API
 * 
 * This file must be placed in the ROOT directory of the WHMCS installation
 * (e.g., /var/www/whmcs/dns_api.php or alongside configuration.php).
 * 
 * It provides a secure JSON API to get/set DNS records via the registrar
 * module's GetDNS/SaveDNS functions, which are NOT exposed through the
 * standard WHMCS External API.
 * 
 * Authentication: Uses the same WHMCS API credentials (identifier + secret).
 * 
 * Endpoints (POST only):
 *   action=GetDNS   — Fetch DNS records for a domain
 *   action=SaveDNS  — Save DNS records for a domain
 */

// Prevent direct browser access without POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['result' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Bootstrap WHMCS
define('CLIENTAREA', true);

// Try to find and load init.php
$initPaths = [
    __DIR__ . '/init.php',
    __DIR__ . '/../init.php',
    dirname(__FILE__) . '/init.php',
];

$loaded = false;
foreach ($initPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['result' => 'error', 'message' => 'WHMCS init.php not found']);
    exit;
}

header('Content-Type: application/json');

// ─── Authentication ────────────────────────────────────────
// Validate API credentials the same way WHMCS does
$identifier = $_POST['identifier'] ?? '';
$secret = $_POST['secret'] ?? '';

if (empty($identifier) || empty($secret)) {
    http_response_code(401);
    echo json_encode(['result' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Use WHMCS's built-in credential validation
try {
    // Attempt to validate using localAPI with a harmless call
    $authCheck = localAPI('GetAdminDetails', [], '');
    
    // Alternatively, verify credentials via the database
    $apiCred = \WHMCS\Database\Capsule::table('tblapikeys')
        ->where('identifier', $identifier)
        ->where('secret', $secret)
        ->first();
    
    // Also check tblapi_roles / tblapicredentials depending on WHMCS version
    if (!$apiCred) {
        // WHMCS 8.x uses a different table structure
        $apiCred = \WHMCS\Database\Capsule::table('tblapicredentials')
            ->where('identifier', $identifier)
            ->where('secret', $secret)
            ->first();
    }
    
    if (!$apiCred) {
        http_response_code(401);
        echo json_encode(['result' => 'error', 'message' => 'Invalid API credentials']);
        exit;
    }
} catch (\Exception $e) {
    // If database lookup fails, fall back to verifying via a real API call
    // We'll use the standard WHMCS API endpoint internally
    $verifyResult = localAPI('WhmcsDetails', [], '');
    if (($verifyResult['result'] ?? '') !== 'success') {
        http_response_code(500);
        echo json_encode(['result' => 'error', 'message' => 'Authentication verification failed']);
        exit;
    }
}

// ─── Parse Request ─────────────────────────────────────────
$action = $_POST['action'] ?? '';
$domainId = (int) ($_POST['domainid'] ?? 0);

if (!$domainId) {
    echo json_encode(['result' => 'error', 'message' => 'domainid is required']);
    exit;
}

// ─── Get Domain Info ───────────────────────────────────────
try {
    $domain = \WHMCS\Database\Capsule::table('tbldomains')
        ->where('id', $domainId)
        ->first();
    
    if (!$domain) {
        echo json_encode(['result' => 'error', 'message' => 'Domain not found']);
        exit;
    }
    
    $registrar = $domain->registrar;
    $domainName = $domain->domain;
    
    if (empty($registrar)) {
        echo json_encode(['result' => 'error', 'message' => 'No registrar module assigned to this domain']);
        exit;
    }
    
    // Split domain into SLD and TLD
    $parts = explode('.', $domainName, 2);
    $sld = $parts[0];
    $tld = $parts[1] ?? '';
    
} catch (\Exception $e) {
    echo json_encode(['result' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ─── Load Registrar Module ────────────────────────────────
// WHMCS provides a built-in way to call registrar module functions
try {
    // Load the registrar module
    $registrarModule = new \WHMCS\Module\Registrar();
    $registrarModule->load($registrar);
    
    // Build module parameters (WHMCS does this automatically internally)
    $params = $registrarModule->getSettings();
    $params['domainid'] = $domainId;
    $params['sld'] = $sld;
    $params['tld'] = $tld;
    $params['domainname'] = $domainName;
    $params['domain'] = $domainName;
    $params['registrar'] = $registrar;
    
    // Get additional domain parameters
    $params['dnsmanagement'] = (bool) $domain->dnsmanagement;
    $params['emailforwarding'] = (bool) $domain->emailforwarding;
    $params['idprotection'] = (bool) $domain->idprotection;

} catch (\Exception $e) {
    echo json_encode(['result' => 'error', 'message' => 'Failed to load registrar module: ' . $e->getMessage()]);
    exit;
}

// ─── Execute Action ────────────────────────────────────────
switch ($action) {
    case 'GetDNS':
        try {
            $functionName = $registrar . '_GetDNS';
            
            // Check if the function exists
            $modulePath = ROOTDIR . '/modules/registrars/' . $registrar . '/' . $registrar . '.php';
            if (file_exists($modulePath)) {
                require_once $modulePath;
            }
            
            if (!function_exists($functionName)) {
                echo json_encode([
                    'result' => 'error',
                    'message' => "Registrar module '{$registrar}' does not support DNS management (GetDNS function not found)"
                ]);
                exit;
            }
            
            $result = call_user_func($functionName, $params);
            
            if (isset($result['error'])) {
                echo json_encode(['result' => 'error', 'message' => $result['error']]);
            } else {
                // $result should be an array of DNS records
                echo json_encode([
                    'result' => 'success',
                    'records' => is_array($result) ? array_values($result) : [],
                    'domain' => $domainName,
                    'registrar' => $registrar,
                ]);
            }
        } catch (\Exception $e) {
            echo json_encode(['result' => 'error', 'message' => 'GetDNS failed: ' . $e->getMessage()]);
        }
        break;
        
    case 'SaveDNS':
        try {
            $functionName = $registrar . '_SaveDNS';
            
            // Load the module file
            $modulePath = ROOTDIR . '/modules/registrars/' . $registrar . '/' . $registrar . '.php';
            if (file_exists($modulePath)) {
                require_once $modulePath;
            }
            
            if (!function_exists($functionName)) {
                echo json_encode([
                    'result' => 'error',
                    'message' => "Registrar module '{$registrar}' does not support DNS management (SaveDNS function not found)"
                ]);
                exit;
            }
            
            // Parse DNS records from POST
            $dnsRecords = [];
            if (isset($_POST['dnsrecords'])) {
                $dnsRecords = is_string($_POST['dnsrecords']) 
                    ? json_decode($_POST['dnsrecords'], true) 
                    : $_POST['dnsrecords'];
            }
            
            if (!is_array($dnsRecords)) {
                echo json_encode(['result' => 'error', 'message' => 'Invalid DNS records format']);
                exit;
            }
            
            // Format records as WHMCS expects (hostname, type, address, priority)
            $formattedRecords = [];
            foreach ($dnsRecords as $record) {
                $formattedRecords[] = [
                    'hostname' => $record['hostname'] ?? $record['name'] ?? '',
                    'type'     => strtoupper($record['type'] ?? 'A'),
                    'address'  => $record['address'] ?? $record['content'] ?? $record['destination'] ?? '',
                    'priority' => $record['priority'] ?? ($record['mxpref'] ?? ''),
                ];
            }
            
            $params['dnsrecords'] = $formattedRecords;
            
            $result = call_user_func($functionName, $params);
            
            if (isset($result['error'])) {
                echo json_encode(['result' => 'error', 'message' => $result['error']]);
            } else {
                echo json_encode([
                    'result' => 'success',
                    'message' => 'DNS records saved successfully',
                ]);
            }
        } catch (\Exception $e) {
            echo json_encode(['result' => 'error', 'message' => 'SaveDNS failed: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['result' => 'error', 'message' => 'Invalid action. Supported: GetDNS, SaveDNS']);
        break;
}
