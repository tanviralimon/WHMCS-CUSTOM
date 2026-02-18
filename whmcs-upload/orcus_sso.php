<?php
/**
 * Orcus SSO Proxy — Upload to WHMCS root directory
 *
 * This file goes at: https://dash.orcustech.com/orcus_sso.php
 *
 * It generates direct SSO login URLs for control panels (SPanel, cPanel, etc.)
 * by reading server credentials from WHMCS database and calling the panel's
 * SSO API directly — bypassing WHMCS's own SSO which only logs into WHMCS clientarea.
 *
 * Supported modules: spanel, cpanel, plesk, directadmin, virtualizor
 */

// ── Bootstrap WHMCS ────────────────────────────────────────
$whmcsDir = __DIR__;
if (!file_exists($whmcsDir . '/init.php')) {
    header('Content-Type: application/json');
    echo json_encode(['result' => 'error', 'message' => 'WHMCS init.php not found.']);
    exit;
}

define('CLIENTAREA', true);
ob_start();
require_once $whmcsDir . '/init.php';
ob_end_clean();

use WHMCS\Database\Capsule;

header('Content-Type: application/json');

// ── Authenticate ───────────────────────────────────────────
$identifier = $_POST['identifier'] ?? '';
$secret     = $_POST['secret'] ?? '';

if (empty($identifier) || empty($secret)) {
    echo json_encode(['result' => 'error', 'message' => 'Authentication required']);
    exit;
}

$validCred = false;
try {
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
            continue;
        }
    }
    if (!$validCred) {
        $expectedId     = 'OvW1qayQgHu3mYa1UiqgCaOW0zrBKhQT';
        $expectedSecret = 'XHI9r0iN5zLMqIfd7AWUMsm4MKpymVxZ';
        if ($identifier === $expectedId && $secret === $expectedSecret) {
            $validCred = true;
        }
    }
} catch (\Exception $e) {
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

// ── Get parameters ─────────────────────────────────────────
$action    = $_POST['action'] ?? '';
$serviceId = (int) ($_POST['serviceid'] ?? 0);
$clientId  = (int) ($_POST['clientid'] ?? 0);

if ($action === 'GetServiceInfo') {
    // Return server module info + SSO capabilities for a service
    if (!$serviceId) {
        echo json_encode(['result' => 'error', 'message' => 'serviceid is required']);
        exit;
    }

    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$service) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }

    // Verify client ownership
    if ($clientId && $service->userid != $clientId) {
        echo json_encode(['result' => 'error', 'message' => 'Service does not belong to client']);
        exit;
    }

    $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();

    $module = '';
    if ($server) {
        $module = strtolower($server->type ?? '');
    }
    if (!$module && $product) {
        $module = strtolower($product->servertype ?? '');
    }

    // Build control panel URL based on module
    $panelUrl    = null;
    $webmailUrl  = null;
    $hostname    = $server->hostname ?? '';
    $ip          = $server->ipaddress ?? '';
    $host        = $hostname ?: $ip;
    $spanelFolder = '';

    switch ($module) {
        case 'spanel':
            // SPanel whitelabel: folder name stored in server username field
            // (e.g. "hostpanel" instead of default "spanel")
            $spanelFolder = !empty($server->username) ? trim($server->username) : 'spanel';
            $panelUrl   = 'https://' . $host . '/' . $spanelFolder . '/';
            $webmailUrl = 'https://' . $host . '/' . $spanelFolder . '/login/webmail';
            break;
        case 'cpanel':
            $panelUrl = 'https://' . $host . ':2083';
            $webmailUrl = 'https://' . $host . ':2096';
            break;
        case 'plesk':
            $panelUrl = 'https://' . $host . ':8443';
            $webmailUrl = 'https://' . $host . '/webmail';
            break;
        case 'directadmin':
            $panelUrl = 'https://' . $host . ':2222';
            $webmailUrl = 'https://' . $host . '/roundcube';
            break;
        case 'virtualizor':
            $panelUrl = 'https://' . $host . ':4083';
            break;
        case 'proxmox':
            $panelUrl = 'https://' . $host . ':8006';
            break;
        case 'solusvm':
            $panelUrl = 'https://' . $host . ':5656';
            break;
    }

    echo json_encode([
        'result'       => 'success',
        'module'       => $module,
        'hostname'     => $hostname,
        'ip'           => $ip,
        'username'     => $service->username ?? '',
        'panelUrl'     => $panelUrl,
        'webmailUrl'   => $webmailUrl,
        'serverName'   => $server->name ?? '',
        'serverId'     => $service->server,
        'spanelFolder' => $spanelFolder,
        'ssoSupported' => in_array($module, ['spanel', 'cpanel', 'virtualizor']),
    ]);
    exit;
}

if ($action === 'SsoLogin') {
    // Generate a direct SSO login URL for the control panel
    if (!$serviceId) {
        echo json_encode(['result' => 'error', 'message' => 'serviceid is required']);
        exit;
    }

    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$service) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }

    // Verify client ownership
    if ($clientId && $service->userid != $clientId) {
        echo json_encode(['result' => 'error', 'message' => 'Service does not belong to client']);
        exit;
    }

    $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    if (!$server) {
        echo json_encode(['result' => 'error', 'message' => 'Server not found for this service']);
        exit;
    }

    $module   = strtolower($server->type ?? '');
    $hostname = $server->hostname ?: $server->ipaddress;
    $username = $service->username ?? '';
    $redirect = $_POST['redirect'] ?? ''; // SPanel: "category/page" e.g. "file/manager"

    // Virtualizor doesn't need a service username — it uses VPS IDs from custom fields
    // Only enforce username check for hosting panels (SPanel, cPanel, etc.)
    if (!$username && !in_array($module, ['virtualizor', 'proxmox', 'solusvm'])) {
        echo json_encode(['result' => 'error', 'message' => 'No username associated with this service']);
        exit;
    }

    switch ($module) {
        case 'spanel':
            echo json_encode(handleSPanelSso($server, $service, $hostname, $username, $redirect));
            exit;

        case 'cpanel':
            echo json_encode(handleCPanelSso($server, $service, $hostname, $username));
            exit;

        case 'virtualizor':
            echo json_encode(handleVirtualizorSso($server, $service, $hostname));
            exit;

        default:
            // For unsupported modules, fall back to WHMCS SSO
            $result = localAPI('CreateSsoToken', [
                'client_id'   => $service->userid,
                'service_id'  => $serviceId,
                'destination' => 'clientarea:product_details',
            ]);
            echo json_encode([
                'result'       => $result['result'] ?? 'error',
                'redirect_url' => $result['redirect_url'] ?? '',
                'message'      => $result['message'] ?? 'SSO not available for this module',
                'module'       => $module,
                'sso_type'     => 'whmcs_fallback',
            ]);
            exit;
    }
}

// ── VPS Actions (Virtualizor / Proxmox) ────────────────────
if ($action === 'VpsAction') {
    if (!$serviceId) {
        echo json_encode(['result' => 'error', 'message' => 'serviceid is required']);
        exit;
    }

    $vpsAction = strtolower(trim($_POST['vps_action'] ?? ''));
    $allowed   = ['boot', 'reboot', 'shutdown', 'resetpassword', 'vnc', 'console', 'start', 'stop', 'restart'];
    if (!in_array($vpsAction, $allowed)) {
        echo json_encode(['result' => 'error', 'message' => 'Invalid VPS action: ' . $vpsAction]);
        exit;
    }

    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$service) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }

    if ($clientId && $service->userid != $clientId) {
        echo json_encode(['result' => 'error', 'message' => 'Service does not belong to client']);
        exit;
    }

    $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    if (!$server) {
        echo json_encode(['result' => 'error', 'message' => 'Server not found']);
        exit;
    }

    $module   = strtolower($server->type ?? '');
    $hostname = $server->hostname ?: $server->ipaddress;

    if ($module === 'virtualizor') {
        echo json_encode(handleVirtualizorAction($server, $service, $hostname, $vpsAction));
        exit;
    }

    // Fallback: try WHMCS ModuleCustom for non-Virtualizor modules
    $result = localAPI('ModuleCustom', [
        'serviceid'  => $serviceId,
        'func_name'  => $vpsAction,
    ]);
    echo json_encode([
        'result'  => $result['result'] ?? 'error',
        'message' => $result['message'] ?? ($result['result'] === 'success' ? 'Action executed' : 'Action failed'),
        'module'  => $module,
    ]);
    exit;
}

// ── Get OS Templates for VPS Rebuild ───────────────────────
if ($action === 'GetOsTemplates') {
    if (!$serviceId) {
        echo json_encode(['result' => 'error', 'message' => 'serviceid is required']);
        exit;
    }

    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$service) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }

    if ($clientId && $service->userid != $clientId) {
        echo json_encode(['result' => 'error', 'message' => 'Service does not belong to client']);
        exit;
    }

    $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    if (!$server) {
        echo json_encode(['result' => 'error', 'message' => 'Server not found']);
        exit;
    }

    $module   = strtolower($server->type ?? '');
    $hostname = $server->hostname ?: $server->ipaddress;

    if ($module !== 'virtualizor') {
        echo json_encode(['result' => 'error', 'message' => 'OS template listing only supported for Virtualizor']);
        exit;
    }

    echo json_encode(handleGetOsTemplates($server, $service, $hostname));
    exit;
}

// ── Rebuild VPS (Reinstall OS) ─────────────────────────────
if ($action === 'RebuildVps') {
    if (!$serviceId) {
        echo json_encode(['result' => 'error', 'message' => 'serviceid is required']);
        exit;
    }

    $osId    = (int) ($_POST['osid'] ?? 0);
    $newPass = $_POST['newpass'] ?? '';

    if (!$osId) {
        echo json_encode(['result' => 'error', 'message' => 'OS template ID (osid) is required']);
        exit;
    }
    if (empty($newPass) || strlen($newPass) < 6) {
        echo json_encode(['result' => 'error', 'message' => 'New password is required (minimum 6 characters)']);
        exit;
    }

    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$service) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }

    if ($clientId && $service->userid != $clientId) {
        echo json_encode(['result' => 'error', 'message' => 'Service does not belong to client']);
        exit;
    }

    $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    if (!$server) {
        echo json_encode(['result' => 'error', 'message' => 'Server not found']);
        exit;
    }

    $module   = strtolower($server->type ?? '');
    $hostname = $server->hostname ?: $server->ipaddress;

    if ($module !== 'virtualizor') {
        echo json_encode(['result' => 'error', 'message' => 'Rebuild only supported for Virtualizor']);
        exit;
    }

    echo json_encode(handleRebuildVps($server, $service, $hostname, $osId, $newPass));
    exit;
}

// ── Debug: Test Virtualizor API connection ─────────────────
if ($action === 'TestVirtApi') {
    // Supports three lookup modes:
    // 1. serviceid  — WHMCS hosting service ID (tblhosting.id)
    // 2. vps_uuid   — Virtualizor VPS UUID (finds server + tests with UUID)
    // 3. vpsid      — Virtualizor VPS ID (finds server + tests with VPSID)
    $vpsUuid  = trim($_POST['vps_uuid'] ?? '');
    $vpsIdArg = (int) ($_POST['vpsid'] ?? 0);

    $server = null;

    if ($serviceId) {
        // Mode 1: Lookup via WHMCS service ID
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) {
            echo json_encode(['result' => 'error', 'message' => 'Service not found']);
            exit;
        }
        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    } elseif ($vpsUuid || $vpsIdArg) {
        // Mode 2/3: Find any Virtualizor server directly
        // Look for servers with type 'virtualizor' (case-insensitive)
        $servers = Capsule::table('tblservers')
            ->whereRaw('LOWER(type) = ?', ['virtualizor'])
            ->where('disabled', 0)
            ->get();

        if ($servers->isEmpty()) {
            // Try without the disabled check
            $servers = Capsule::table('tblservers')
                ->whereRaw('LOWER(type) = ?', ['virtualizor'])
                ->get();
        }

        if ($servers->isEmpty()) {
            echo json_encode(['result' => 'error', 'message' => 'No Virtualizor server found in WHMCS']);
            exit;
        }

        // If multiple servers, test each until we find the VPS
        $server = $servers->first();
    } else {
        echo json_encode(['result' => 'error', 'message' => 'serviceid, vps_uuid, or vpsid is required']);
        exit;
    }

    if (!$server) {
        echo json_encode(['result' => 'error', 'message' => 'Server not found']);
        exit;
    }

    $hostname = $server->hostname ?: $server->ipaddress;
    $creds = getVirtualizorCredentials($server);

    // Check which WHMCS classes are available
    $available = [
        'ServerModel'       => class_exists('\\WHMCS\\Product\\Server\\Server'),
        'SecurityEncryption' => class_exists('\\WHMCS\\Security\\Encryption'),
        'decrypt_func'      => function_exists('decrypt'),
        'localAPI_func'     => function_exists('localAPI'),
    ];

    // Test 1: Basic API connectivity (list VPS)
    $queryStr = http_build_query([
        'act'          => 'vs',
        'api'          => 'json',
        'adminapikey'  => $creds['apiKey'],
        'adminapipass' => $creds['apiPass'],
    ]);

    // Virtualizor admin panel runs on port 4085 over HTTPS
    // Try hostname first, then IP
    $testUrl = 'https://' . $hostname . ':4085/index.php?' . $queryStr;
    $testResult = virtualizorApiGet($testUrl);
    $usedProtocol = 'https';

    // If hostname failed, try with server IP
    if (!$testResult['ok'] && !empty($server->ipaddress) && $server->ipaddress !== $hostname) {
        $ipUrl = 'https://' . $server->ipaddress . ':4085/index.php?' . $queryStr;
        $ipResult = virtualizorApiGet($ipUrl);
        if ($ipResult['ok']) {
            $testResult = $ipResult;
            $hostname = $server->ipaddress;
            $usedProtocol = 'https_ip';
        }
    }

    // Test 2: If UUID or VPSID given, try fetching that specific VPS status
    $vpsTest = null;
    $resolvedVpsId = $vpsIdArg;

    if ($testResult['ok'] && ($vpsUuid || $vpsIdArg)) {
        // If we have UUID but no VPSID, find VPSID from the VPS list
        if ($vpsUuid && !$resolvedVpsId && !empty($testResult['data'])) {
            foreach ($testResult['data'] as $key => $vps) {
                if (is_array($vps) && isset($vps['uuid']) && $vps['uuid'] === $vpsUuid) {
                    $resolvedVpsId = (int) ($vps['vpsid'] ?? $key);
                    break;
                }
            }
        }

        if ($resolvedVpsId) {
            // Get live status for this specific VPS
            $statusUrl = 'https://' . $hostname . ':4085/index.php?' . http_build_query([
                'act'          => 'vs',
                'vs_status'    => $resolvedVpsId,
                'api'          => 'json',
                'adminapikey'  => $creds['apiKey'],
                'adminapipass' => $creds['apiPass'],
            ]);
            $statusResult = virtualizorApiGet($statusUrl);
            $vpsTest = [
                'vpsid'       => $resolvedVpsId,
                'uuid_input'  => $vpsUuid ?: null,
                'status_ok'   => $statusResult['ok'],
                'status_code' => $statusResult['http_code'] ?? 0,
                'status_error' => $statusResult['error'] ?? null,
                'status_data' => $statusResult['ok'] ? $statusResult['data'] : null,
            ];
        } else {
            $vpsTest = [
                'vpsid'      => 0,
                'uuid_input' => $vpsUuid ?: null,
                'error'      => $vpsUuid
                    ? 'UUID not found in VPS list. VPS may be on a different server.'
                    : 'VPSID not resolved',
            ];
        }
    }

    // Also try to find the WHMCS service ID that maps to this VPS
    $whmcsServiceId = null;
    if ($resolvedVpsId) {
        try {
            $cfMatch = Capsule::table('tblcustomfieldsvalues as cv')
                ->join('tblcustomfields as cf', 'cv.fieldid', '=', 'cf.id')
                ->where('cv.value', (string) $resolvedVpsId)
                ->where('cf.type', 'product')
                ->whereRaw('LOWER(cf.fieldname) IN (?, ?, ?, ?)', ['vpsid', 'vps id', 'vserverid', 'vps_id'])
                ->select('cv.relid')
                ->first();
            if ($cfMatch) {
                $whmcsServiceId = (int) $cfMatch->relid;
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    echo json_encode([
        'result'     => 'success',
        'test'       => [
            'hostname'           => $hostname,
            'server_hostname'    => $server->hostname ?? '',
            'server_ip'          => $server->ipaddress ?? '',
            'server_id'          => $server->id ?? 0,
            'server_type'        => $server->type ?? '',
            'protocol_used'      => $usedProtocol,
            'username_field'     => !empty($server->username) ? substr($server->username, 0, 8) . '...' : '(empty)',
            'password_field_len' => strlen($server->password ?? ''),
            'accesshash_field'   => !empty($server->accesshash) ? 'set (' . strlen($server->accesshash) . ' chars)' : '(empty)',
            'api_key_length'     => strlen($creds['apiKey']),
            'api_key_first8'     => strlen($creds['apiKey']) > 8 ? substr($creds['apiKey'], 0, 8) . '...' : $creds['apiKey'],
            'api_pass_length'    => strlen($creds['apiPass']),
            'api_pass_first8'    => strlen($creds['apiPass']) > 8 ? substr($creds['apiPass'], 0, 8) . '...' : $creds['apiPass'],
            'decrypt_method'     => $creds['method'],
            'available_methods'  => $available,
            'api_call_ok'        => $testResult['ok'] ?? false,
            'api_http_code'      => $testResult['http_code'] ?? 0,
            'api_error'          => $testResult['error'] ?? null,
            'api_data_keys'      => $testResult['ok'] ? array_keys($testResult['data']) : null,
            'vps_test'           => $vpsTest,
            'whmcs_service_id'   => $whmcsServiceId,
        ],
    ]);
    exit;
}

// ── VPS Stats (Virtualizor) ────────────────────────────────
if ($action === 'GetVpsStats') {
    if (!$serviceId) {
        echo json_encode(['result' => 'error', 'message' => 'serviceid is required']);
        exit;
    }

    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
    if (!$service) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }

    if ($clientId && $service->userid != $clientId) {
        echo json_encode(['result' => 'error', 'message' => 'Service does not belong to client']);
        exit;
    }

    $server = Capsule::table('tblservers')->where('id', $service->server)->first();
    if (!$server) {
        echo json_encode(['result' => 'error', 'message' => 'Server not found']);
        exit;
    }

    $module   = strtolower($server->type ?? '');
    $hostname = $server->hostname ?: $server->ipaddress;

    if ($module === 'virtualizor') {
        echo json_encode(handleVirtualizorStats($server, $service, $hostname));
        exit;
    }

    echo json_encode(['result' => 'error', 'message' => 'VPS stats not supported for module: ' . $module]);
    exit;
}

if ($action === 'GetGatewayConfig') {
    // Return payment gateway module configuration.
    // Uses WHMCS's built-in getGatewayVariables() which handles decryption
    // of password-type fields (API keys, secrets, etc.) automatically.
    $gatewayModule = trim($_POST['gateway'] ?? '');

    if (empty($gatewayModule)) {
        echo json_encode(['result' => 'error', 'message' => 'gateway parameter is required']);
        exit;
    }

    try {
        // Load WHMCS gateway functions (includes getGatewayVariables)
        App::load_function('gateway');

        // getGatewayVariables() reads tblpaymentgateways AND decrypts
        // password-type fields automatically. Returns all config params.
        $params = getGatewayVariables($gatewayModule);

        if (empty($params) || empty($params['type'])) {
            echo json_encode(['result' => 'error', 'message' => 'Gateway module is not active: ' . $gatewayModule]);
            exit;
        }

        // Filter out internal WHMCS fields, keep only module config settings
        $skip = ['type', 'visible', 'name', 'paymentmethod', 'companyname',
                 'systemurl', 'langpaynow', 'whmcsVersion', 'convertto'];
        $settings = [];
        foreach ($params as $key => $value) {
            if (in_array($key, $skip)) {
                continue;
            }
            $settings[$key] = $value;
        }

        echo json_encode([
            'result'   => 'success',
            'gateway'  => $gatewayModule,
            'settings' => $settings,
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'result'  => 'error',
            'message' => 'Failed to read gateway config: ' . $e->getMessage(),
        ]);
    }
    exit;
}

if ($action === 'GetProductGroups') {
    // Return product groups from tblproductgroups, ordered by the 'order' column.
    // This gives us group names that GetProducts API doesn't include.
    // Returns ALL groups (including hidden flag) so the Laravel side can filter.
    try {
        $groups = Capsule::table('tblproductgroups')
            ->select('id', 'name', 'slug', 'headline', 'tagline', 'orderfrmtpl', 'hidden', 'order')
            ->orderBy('order', 'asc')
            ->get();

        $result = [];
        foreach ($groups as $g) {
            $result[] = [
                'id'       => (int) $g->id,
                'name'     => $g->name,
                'slug'     => $g->slug ?? '',
                'headline' => $g->headline ?? '',
                'tagline'  => $g->tagline ?? '',
                'hidden'   => !empty($g->hidden),
                'order'    => (int) ($g->order ?? 0),
            ];
        }

        echo json_encode([
            'result'       => 'success',
            'totalresults' => count($result),
            'groups'       => $result,
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'result'  => 'error',
            'message' => 'Failed to read product groups: ' . $e->getMessage(),
        ]);
    }
    exit;
}

echo json_encode(['result' => 'error', 'message' => 'Invalid action. Use: GetServiceInfo, SsoLogin, GetGatewayConfig, GetProductGroups']);
exit;


// ═══════════════════════════════════════════════════════════
// SPanel SSO Handler
// ═══════════════════════════════════════════════════════════
function handleSPanelSso($server, $service, $hostname, $username, $redirect = '')
{
    // SPanel API token is stored in the server's accesshash field
    $apiToken = '';

    // Try accesshash first (where WHMCS stores it per SPanel docs)
    if (!empty($server->accesshash)) {
        $apiToken = trim($server->accesshash);
    }

    if (empty($apiToken)) {
        return [
            'result'  => 'error',
            'message' => 'SPanel API token not configured on server. Check WHMCS server Access Hash.',
            'module'  => 'spanel',
        ];
    }

    // SPanel folder name is stored in the server's username field
    // (per WHMCS module docs: "Enter your server's SPanel folder inside the Username field")
    $spanelFolder = !empty($server->username) ? trim($server->username) : 'spanel';

    // Call SPanel SSO API: https://hostname/{folder}/api.php
    $endpointUrl = 'https://' . $hostname . '/' . $spanelFolder . '/api.php';

    $postData = [
        'token'    => $apiToken,
        'username' => $username,
        'role'     => 'user',
        'action'   => 'base/sso',
    ];

    // SPanel SSO supports redirect parameter in "category/page" format
    // Known working pages: file/filemanager, email/emailaccounts, database/mysqldatabases,
    //   tool/sslcertificates, domain/addondomains, domain/dnszones
    if (!empty($redirect)) {
        $postData['redirect'] = $redirect;
    }

    $ssoResult = callSPanelSsoApi($endpointUrl, $postData);

    // If SSO with redirect failed (e.g. "Unknown page or category"), retry WITHOUT redirect
    // so the user at least gets logged into the panel home
    if ($ssoResult['result'] !== 'success' && !empty($redirect)) {
        unset($postData['redirect']);
        $ssoResult = callSPanelSsoApi($endpointUrl, $postData);
    }

    return $ssoResult;
}

/**
 * Helper: Make the actual cURL call to SPanel SSO API
 */
function callSPanelSsoApi($endpointUrl, $postData)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpointUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'result'  => 'error',
            'message' => 'Failed to connect to SPanel: ' . $curlError,
            'module'  => 'spanel',
        ];
    }

    $data = json_decode($response, true);

    if (!$data) {
        return [
            'result'  => 'error',
            'message' => 'Invalid response from SPanel API (HTTP ' . $httpCode . ')',
            'module'  => 'spanel',
        ];
    }

    if (($data['result'] ?? '') === 'success' && !empty($data['data']['url'])) {
        return [
            'result'       => 'success',
            'redirect_url' => $data['data']['url'],
            'module'       => 'spanel',
            'sso_type'     => 'spanel_direct',
        ];
    }

    return [
        'result'  => 'error',
        'message' => 'SPanel SSO failed: ' . json_encode($data['message'] ?? $data),
        'module'  => 'spanel',
    ];
}


// ═══════════════════════════════════════════════════════════
// cPanel SSO Handler
// ═══════════════════════════════════════════════════════════
function handleCPanelSso($server, $service, $hostname, $username)
{
    // cPanel uses WHM API to create a session for the user
    // WHM runs on port 2087, and we need the root password or access hash
    $accessHash = '';
    $whmUser    = '';
    $whmPass    = '';

    if (!empty($server->accesshash)) {
        $accessHash = trim(str_replace(["\r", "\n"], '', $server->accesshash));
    }
    if (!empty($server->username)) {
        $whmUser = $server->username;
    }
    if (!empty($server->password)) {
        // WHMCS encrypts passwords — decrypt it
        $whmPass = decrypt($server->password);
    }

    // Try WHM API to create a user session
    $whmHost = 'https://' . $hostname . ':2087';

    $headers = [];
    if ($accessHash) {
        $headers[] = 'Authorization: WHM root:' . $accessHash;
    } elseif ($whmUser && $whmPass) {
        $headers[] = 'Authorization: Basic ' . base64_encode($whmUser . ':' . $whmPass);
    } else {
        return [
            'result'  => 'error',
            'message' => 'No WHM credentials available for cPanel SSO',
            'module'  => 'cpanel',
        ];
    }

    // WHM API: create_user_session
    $url = $whmHost . '/json-api/create_user_session?api.version=1&user=' . urlencode($username) . '&service=cpaneld';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'result'  => 'error',
            'message' => 'Failed to connect to WHM: ' . $curlError,
            'module'  => 'cpanel',
        ];
    }

    $data = json_decode($response, true);

    if (!empty($data['data']['url'])) {
        return [
            'result'       => 'success',
            'redirect_url' => $data['data']['url'],
            'module'       => 'cpanel',
            'sso_type'     => 'cpanel_direct',
        ];
    }

    return [
        'result'  => 'error',
        'message' => 'cPanel SSO failed: ' . json_encode($data['errors'] ?? $data['metadata'] ?? $data),
        'module'  => 'cpanel',
    ];
}


// ═══════════════════════════════════════════════════════════
// Virtualizor SSO Handler
// ═══════════════════════════════════════════════════════════
function handleVirtualizorSso($server, $service, $hostname)
{
    // Virtualizor Admin API credentials stored in WHMCS server config:
    // - server username = API Key
    // - server password = API Pass (encrypted by WHMCS)
    // Admin panel runs on port 4085
    $creds = getVirtualizorCredentials($server);
    $apiKey  = $creds['apiKey'];
    $apiPass = $creds['apiPass'];

    if (empty($apiKey) || empty($apiPass)) {
        // Fallback: direct panel URL without SSO
        $panelUrl = 'https://' . $hostname . ':4083';
        return [
            'result'       => 'success',
            'redirect_url' => $panelUrl,
            'module'       => 'virtualizor',
            'sso_type'     => 'direct_url',
            'message'      => 'SSO not available — opening panel login page',
        ];
    }

    // The Virtualizor WHMCS module stores the VPSID in the service's
    // customfields or in the "domain" field. Check tblcustomfieldsvalues.
    $vpsId = 0;

    // Method 1: Check custom fields for "vpsid" / "VPS ID" / "vserverid"
    $customFields = Capsule::table('tblcustomfields')
        ->where('relid', $service->packageid)
        ->where('type', 'product')
        ->get();

    foreach ($customFields as $cf) {
        $fieldName = strtolower($cf->fieldname);
        if (in_array($fieldName, ['vpsid', 'vps id', 'vserverid', 'vps_id'])) {
            $cfVal = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $cf->id)
                ->where('relid', $service->id)
                ->value('value');
            if (!empty($cfVal)) {
                $vpsId = (int) $cfVal;
                break;
            }
        }
    }

    // Method 2: Virtualizor module stores VPSID in tblhosting.domain or
    // in a custom field named "vserverid". Also check the domain field.
    if (!$vpsId && !empty($service->domain) && is_numeric($service->domain)) {
        $vpsId = (int) $service->domain;
    }

    // Method 3: Try querying Virtualizor API to find VPS by service username/email
    if (!$vpsId) {
        // Check if there's a field called vserverid in customfields
        foreach ($customFields as $cf) {
            $fieldName = strtolower($cf->fieldname);
            if (str_contains($fieldName, 'server') || str_contains($fieldName, 'vps')) {
                $cfVal = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $cf->id)
                    ->where('relid', $service->id)
                    ->value('value');
                if (!empty($cfVal) && is_numeric($cfVal)) {
                    $vpsId = (int) $cfVal;
                    break;
                }
            }
        }
    }

    if (!$vpsId) {
        // If we still can't find the VPSID, fall back to direct panel URL
        $panelUrl = 'https://' . $hostname . ':4083';
        return [
            'result'       => 'success',
            'redirect_url' => $panelUrl,
            'module'       => 'virtualizor',
            'sso_type'     => 'direct_url',
            'message'      => 'VPS ID not found — opening panel login page',
        ];
    }

    // Call Virtualizor Admin API to create an SSO session for the enduser
    // Admin API: https://hostname:4085/index.php?act=sso&api=json&apikey=KEY&apipass=PASS
    $adminUrl = 'https://' . $hostname . ':4085/index.php';

    $params = [
        'act'          => 'sso',
        'api'          => 'json',
        'adminapikey'  => $apiKey,
        'adminapipass' => $apiPass,
        'vpsid'        => $vpsId,
    ];

    $url = $adminUrl . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        // Fallback to direct URL
        return [
            'result'       => 'success',
            'redirect_url' => 'https://' . $hostname . ':4083',
            'module'       => 'virtualizor',
            'sso_type'     => 'direct_url',
            'message'      => 'Virtualizor API connection failed: ' . $curlError,
        ];
    }

    // Virtualizor SSO API output is a raw URL string (not JSON), e.g.:
    // https://hostname:4085/sessXXXX/?as=TOKEN
    // When api=json is passed, it MAY return JSON wrapping the URL.
    // Handle both cases:

    // Case 1: Response is a raw URL string (most common from SSO API)
    $trimmed = trim($response);
    if (preg_match('#^https?://.+/sess[^/]+/\?as=.+#', $trimmed)) {
        // Direct SSO URL — rewrite port 4085 (admin) to 4083 (enduser)
        $ssoUrl = str_replace(':4085/', ':4083/', $trimmed);
        return [
            'result'       => 'success',
            'redirect_url' => $ssoUrl,
            'module'       => 'virtualizor',
            'sso_type'     => 'virtualizor_sso',
        ];
    }

    // Case 2: JSON response
    $data = json_decode($response, true);

    if (is_array($data)) {
        // Check if 'done' contains the SSO URL or token
        if (!empty($data['done'])) {
            $done = $data['done'];
            // If 'done' is a full URL
            if (is_string($done) && str_starts_with($done, 'http')) {
                $ssoUrl = str_replace(':4085/', ':4083/', $done);
                return [
                    'result'       => 'success',
                    'redirect_url' => $ssoUrl,
                    'module'       => 'virtualizor',
                    'sso_type'     => 'virtualizor_sso',
                ];
            }
            // If 'done' is a token string
            if (is_string($done) && strlen($done) > 5) {
                $ssoUrl = 'https://' . $hostname . ':4083/?sso=' . urlencode($done);
                return [
                    'result'       => 'success',
                    'redirect_url' => $ssoUrl,
                    'module'       => 'virtualizor',
                    'sso_type'     => 'virtualizor_sso',
                ];
            }
        }

        // Check for error
        if (!empty($data['error'])) {
            $errorMsg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
            return [
                'result'       => 'success',
                'redirect_url' => 'https://' . $hostname . ':4083',
                'module'       => 'virtualizor',
                'sso_type'     => 'direct_url',
                'message'      => 'Virtualizor SSO error: ' . $errorMsg,
            ];
        }
    }

    // Case 3: Response might be any URL (non-standard SSO response)
    if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return [
            'result'       => 'success',
            'redirect_url' => $trimmed,
            'module'       => 'virtualizor',
            'sso_type'     => 'virtualizor_sso',
        ];
    }

    // Unknown response — fallback to direct URL
    return [
        'result'       => 'success',
        'redirect_url' => 'https://' . $hostname . ':4083',
        'module'       => 'virtualizor',
        'sso_type'     => 'direct_url',
        'message'      => 'Unexpected Virtualizor SSO response',
        'debug'        => substr($response, 0, 300),
    ];
}


// ═══════════════════════════════════════════════════════════
// Virtualizor VPS Action Handler (boot, reboot, shutdown, etc.)
// ═══════════════════════════════════════════════════════════
function handleVirtualizorAction($server, $service, $hostname, $vpsAction)
{
    // Extract API credentials — try multiple approaches for WHMCS compatibility
    $creds = getVirtualizorCredentials($server);
    $apiKey  = $creds['apiKey'];
    $apiPass = $creds['apiPass'];

    if (empty($apiKey) || empty($apiPass)) {
        return [
            'result'  => 'error',
            'message' => 'Virtualizor API credentials not configured on server',
            'debug'   => [
                'username_set' => !empty($server->username),
                'password_set' => !empty($server->password),
                'accesshash_set' => !empty($server->accesshash),
                'decrypt_method' => $creds['method'] ?? 'none',
            ],
        ];
    }

    // Find the VPS ID (same logic as SSO handler)
    $vpsId = 0;

    $customFields = Capsule::table('tblcustomfields')
        ->where('relid', $service->packageid)
        ->where('type', 'product')
        ->get();

    foreach ($customFields as $cf) {
        $fieldName = strtolower($cf->fieldname);
        if (in_array($fieldName, ['vpsid', 'vps id', 'vserverid', 'vps_id'])) {
            $cfVal = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $cf->id)
                ->where('relid', $service->id)
                ->value('value');
            if (!empty($cfVal)) {
                $vpsId = (int) $cfVal;
                break;
            }
        }
    }

    if (!$vpsId && !empty($service->domain) && is_numeric($service->domain)) {
        $vpsId = (int) $service->domain;
    }

    if (!$vpsId) {
        foreach ($customFields as $cf) {
            $fieldName = strtolower($cf->fieldname);
            if (str_contains($fieldName, 'server') || str_contains($fieldName, 'vps')) {
                $cfVal = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $cf->id)
                    ->where('relid', $service->id)
                    ->value('value');
                if (!empty($cfVal) && is_numeric($cfVal)) {
                    $vpsId = (int) $cfVal;
                    break;
                }
            }
        }
    }

    if (!$vpsId) {
        return ['result' => 'error', 'message' => 'VPS ID not found for this service'];
    }

    // Map our action names to Virtualizor Admin API actions
    // Virtualizor Admin API: GET https://hostname:4085/index.php?act=vs&action=ACTION&vpsid=ID&api=json
    // ALL parameters must be in query string (GET), NOT POST body
    $actionMap = [
        'boot'          => 'start',
        'start'         => 'start',
        'reboot'        => 'restart',
        'restart'       => 'restart',
        'shutdown'      => 'stop',
        'stop'          => 'stop',
        'poweroff'      => 'poweroff',
    ];

    // ── Password Reset: Use Virtualizor Manage VPS API (act=managevps) ──
    // The correct API for changing root password is act=managevps with POST rootpass=NEWPASS
    // NOT act=vs&action=resetpassword (which doesn't exist in Virtualizor)
    if ($vpsAction === 'resetpassword') {
        // Generate a secure random password (16 chars, letters + digits + special)
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        $newPassword = '';
        for ($i = 0; $i < 16; $i++) {
            $newPassword .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $adminUrl = 'https://' . $hostname . ':4085/index.php';
        $queryParams = [
            'act'          => 'managevps',
            'vpsid'        => $vpsId,
            'api'          => 'json',
            'adminapikey'  => $apiKey,
            'adminapipass' => $apiPass,
        ];
        $manageUrl = $adminUrl . '?' . http_build_query($queryParams);

        // POST the new root password
        $postResult = virtualizorApiPost($manageUrl, [
            'vpsid'    => $vpsId,
            'rootpass' => $newPassword,
        ]);

        if (!$postResult['ok']) {
            return [
                'result'  => 'error',
                'message' => 'Failed to connect to Virtualizor API: ' . ($postResult['error'] ?? 'Unknown error'),
            ];
        }

        $data = $postResult['data'];

        // Check for errors
        if (!empty($data['error'])) {
            $errors = $data['error'];
            if (is_array($errors)) {
                $errorMsg = implode(', ', array_values($errors));
            } else {
                $errorMsg = (string) $errors;
            }
            return ['result' => 'error', 'message' => 'Virtualizor: ' . $errorMsg];
        }

        // Check for success (Manage VPS returns "done" => { "done" => true })
        if (isset($data['done'])) {
            return [
                'result'       => 'success',
                'message'      => 'Root password has been changed successfully.',
                'action'       => 'resetpassword',
                'module'       => 'virtualizor',
                'new_password' => $newPassword,
            ];
        }

        // If response has no error and no done, but HTTP was 2xx, still might be ok
        if (($postResult['http_code'] ?? 0) >= 200 && ($postResult['http_code'] ?? 0) < 300) {
            if (isset($data['vs_info']) || isset($data['title'])) {
                return [
                    'result'       => 'success',
                    'message'      => 'Root password has been changed successfully.',
                    'action'       => 'resetpassword',
                    'module'       => 'virtualizor',
                    'new_password' => $newPassword,
                ];
            }
        }

        return [
            'result'  => 'error',
            'message' => 'Virtualizor API response unclear. Please verify the password was changed in the Virtualizor panel.',
            'debug'   => array_keys($data ?? []),
        ];
    }

    // VNC / Console: Get VNC info directly via Virtualizor VNC Info API
    // then try SSO for the URL; if SSO fails, build noVNC URL from VNC info
    if ($vpsAction === 'vnc' || $vpsAction === 'console') {
        // Step 1: Get VNC connection info via Admin API
        // Docs: POST act=vnc, novnc=VPSID → {info: {port, ip, password}}
        $adminUrl = 'https://' . $hostname . ':4085/index.php';
        $vncQueryParams = [
            'act'          => 'vnc',
            'api'          => 'json',
            'adminapikey'  => $apiKey,
            'adminapipass' => $apiPass,
        ];
        $vncUrl = $adminUrl . '?' . http_build_query($vncQueryParams);
        $vncResult = virtualizorApiPost($vncUrl, ['novnc' => $vpsId]);

        $vncInfo = null;
        if ($vncResult['ok'] && !empty($vncResult['data']['info'])) {
            $vncInfo = $vncResult['data']['info'];
        }

        // Step 2: Try SSO to build authenticated VNC URL
        $ssoResult = handleVirtualizorSso($server, $service, $hostname);
        $ssoType = $ssoResult['sso_type'] ?? 'direct_url';

        if ($ssoType === 'virtualizor_sso' && !empty($ssoResult['redirect_url'])) {
            // SSO worked — build VNC URL with SSO session
            // Virtualizor enduser panel uses HASH-BASED routing (#act=listvs&)
            // so we must append the VNC destination as a URL fragment, NOT query params.
            // Browsers preserve hash fragments through 302 redirects, so after SSO
            // authenticates and redirects to index.php, the #act=vnc&novnc=ID fragment
            // will load the VNC page automatically.
            $vncRedirectUrl = $ssoResult['redirect_url'] . '#act=vnc&novnc=' . $vpsId;
            return [
                'result'       => 'success',
                'redirect_url' => $vncRedirectUrl,
                'message'      => 'VNC console ready',
                'action'       => 'vnc',
                'module'       => 'virtualizor',
                'sso_type'     => 'virtualizor_sso',
                'vnc_info'     => $vncInfo,
            ];
        }

        // Step 3: SSO failed — use hash-based routing to VNC page (login required)
        // Virtualizor enduser panel uses hash routing: index.php?#act=vnc&novnc=VPSID
        if ($vncInfo) {
            return [
                'result'       => 'success',
                'redirect_url' => 'https://' . $hostname . ':4083/index.php?#act=vnc&novnc=' . $vpsId,
                'message'      => 'VNC console (login required)',
                'action'       => 'vnc',
                'module'       => 'virtualizor',
                'sso_type'     => 'direct_url',
                'vnc_info'     => $vncInfo,
            ];
        }

        // Fallback: direct panel VNC URL with hash routing
        return [
            'result'       => 'success',
            'redirect_url' => 'https://' . $hostname . ':4083/index.php?#act=vnc&novnc=' . $vpsId,
            'message'      => 'VNC console (login required)',
            'action'       => 'vnc',
            'module'       => 'virtualizor',
            'sso_type'     => 'direct_url',
        ];
    }

    $apiAction = $actionMap[$vpsAction] ?? null;
    if (!$apiAction) {
        return ['result' => 'error', 'message' => 'Unsupported action: ' . $vpsAction];
    }

    // Call Virtualizor Admin API — ALL params as GET query string
    // Docs: https://www.virtualizor.com/docs/admin-api/start-vps/
    // Format: GET https://hostname:4085/index.php?act=vs&action=start&vpsid=VPSID&api=json&apikey=KEY&apipass=PASS
    $adminUrl = 'https://' . $hostname . ':4085/index.php';

    // ALL parameters go in query string — Virtualizor ignores POST body for these actions
    $queryParams = [
        'act'          => 'vs',
        'action'       => $apiAction,
        'vpsid'        => $vpsId,
        'api'          => 'json',
        'adminapikey'  => $apiKey,
        'adminapipass' => $apiPass,
    ];

    $url = $adminUrl . '?' . http_build_query($queryParams);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['result' => 'error', 'message' => 'Virtualizor API connection failed: ' . $curlError];
    }

    // HTTP 302 = redirect to login page = auth failed
    if ($httpCode === 302 || $httpCode === 301) {
        return [
            'result'  => 'error',
            'message' => 'Virtualizor API authentication failed (HTTP ' . $httpCode . ' redirect)',
            'debug'   => [
                'hostname'    => $hostname,
                'api_key_len' => strlen($apiKey),
                'api_pass_len' => strlen($apiPass),
            ],
        ];
    }

    $data = json_decode($response, true);

    // If JSON decode failed, return raw response for debugging
    if ($data === null) {
        return [
            'result'  => 'error',
            'message' => 'Virtualizor API returned invalid JSON (HTTP ' . $httpCode . ')',
            'debug'   => substr($response, 0, 500),
        ];
    }

    // Virtualizor API has various success indicators depending on the action:
    // - { "done": true } or { "done": 1 } or { "done": "1" }
    // - { "done": { ... } } (object with details)
    // - Some actions just return the data without error
    // Check for explicit errors first
    if (!empty($data['error'])) {
        $errors = $data['error'];
        if (is_array($errors)) {
            // Virtualizor returns errors as array: ["ERROR_MSG1", "ERROR_MSG2"]
            $errorMsg = implode(', ', array_values($errors));
        } else {
            $errorMsg = (string) $errors;
        }
        return ['result' => 'error', 'message' => 'Virtualizor: ' . $errorMsg];
    }

    // Success: "done" key exists and is truthy, OR no error key present
    $actionLabels = [
        'start'         => 'VPS booted successfully',
        'restart'       => 'VPS rebooted successfully',
        'stop'          => 'VPS shutdown successfully',
        'poweroff'      => 'VPS powered off successfully',
        'resetpassword' => 'Password reset successfully — check your email',
    ];

    // Check for done key (most common success indicator)
    if (isset($data['done'])) {
        return [
            'result'  => 'success',
            'message' => $actionLabels[$apiAction] ?? ucfirst($vpsAction) . ' executed successfully',
            'action'  => $vpsAction,
            'module'  => 'virtualizor',
        ];
    }

    // Some Virtualizor API responses don't have "done" but also no "error"
    // meaning the action was accepted. Check HTTP status and absence of error.
    if ($httpCode >= 200 && $httpCode < 300 && empty($data['error'])) {
        // Check for common success indicators in response
        if (isset($data['vs']) || isset($data['status']) || isset($data['info'])
            || isset($data['output']) || isset($data['timenow']) || isset($data['title'])) {
            return [
                'result'  => 'success',
                'message' => $actionLabels[$apiAction] ?? ucfirst($vpsAction) . ' executed successfully',
                'action'  => $vpsAction,
                'module'  => 'virtualizor',
            ];
        }
    }

    // If we still can't determine success, return the raw response for debugging
    return [
        'result'  => 'error',
        'message' => 'Virtualizor API response unclear for ' . $vpsAction . '. Please check the VPS status in Virtualizor panel.',
        'debug'   => array_keys($data),
    ];
}

// ═══════════════════════════════════════════════════════════
// Virtualizor VPS Stats Handler — Get live VPS information
// Uses both VPS Status API (live CPU/RAM/disk) and List VS API (config details)
// ═══════════════════════════════════════════════════════════
function handleVirtualizorStats($server, $service, $hostname)
{
    // Extract API credentials — try multiple approaches for WHMCS compatibility
    $creds = getVirtualizorCredentials($server);
    $apiKey  = $creds['apiKey'];
    $apiPass = $creds['apiPass'];

    if (empty($apiKey) || empty($apiPass)) {
        return [
            'result'  => 'error',
            'message' => 'Virtualizor API credentials not configured on server',
            'debug'   => [
                'username_set' => !empty($server->username),
                'password_set' => !empty($server->password),
                'accesshash_set' => !empty($server->accesshash),
                'decrypt_method' => $creds['method'] ?? 'none',
            ],
        ];
    }

    // Find the VPS ID (same logic as action/SSO handlers)
    $vpsId = 0;

    $customFields = Capsule::table('tblcustomfields')
        ->where('relid', $service->packageid)
        ->where('type', 'product')
        ->get();

    foreach ($customFields as $cf) {
        $fieldName = strtolower($cf->fieldname);
        if (in_array($fieldName, ['vpsid', 'vps id', 'vserverid', 'vps_id'])) {
            $cfVal = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $cf->id)
                ->where('relid', $service->id)
                ->value('value');
            if (!empty($cfVal)) {
                $vpsId = (int) $cfVal;
                break;
            }
        }
    }

    if (!$vpsId && !empty($service->domain) && is_numeric($service->domain)) {
        $vpsId = (int) $service->domain;
    }

    if (!$vpsId) {
        foreach ($customFields as $cf) {
            $fieldName = strtolower($cf->fieldname);
            if (str_contains($fieldName, 'server') || str_contains($fieldName, 'vps')) {
                $cfVal = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $cf->id)
                    ->where('relid', $service->id)
                    ->value('value');
                if (!empty($cfVal) && is_numeric($cfVal)) {
                    $vpsId = (int) $cfVal;
                    break;
                }
            }
        }
    }

    if (!$vpsId) {
        return ['result' => 'error', 'message' => 'VPS ID not found for this service'];
    }

    $adminUrl = 'https://' . $hostname . ':4085/index.php';

    // ── 1. VPS Status API — live resource usage ──
    // GET https://hostname:4085/index.php?act=vs&vs_status=VPSID&api=json&apikey=KEY&apipass=PASS
    $statusParams = [
        'act'          => 'vs',
        'vs_status'    => $vpsId,
        'api'          => 'json',
        'adminapikey'  => $apiKey,
        'adminapipass' => $apiPass,
    ];

    $statusUrl = $adminUrl . '?' . http_build_query($statusParams);
    $statusResult = virtualizorApiGet($statusUrl);

    if (!$statusResult['ok']) {
        return [
            'result'  => 'error',
            'message' => 'Virtualizor Status API failed: ' . $statusResult['error'],
            'debug'   => [
                'http_code' => $statusResult['http_code'] ?? 0,
                'hostname'  => $hostname,
                'api_key_len' => strlen($apiKey),
                'api_pass_len' => strlen($apiPass),
            ],
        ];
    }
    $statusData = $statusResult['data'];

    // ── 2. List VS API — full VPS config details ──
    // GET https://hostname:4085/index.php?act=vs&api=json&apikey=KEY&apipass=PASS
    // POST: vpsid=ID
    $listParams = [
        'act'          => 'vs',
        'api'          => 'json',
        'adminapikey'  => $apiKey,
        'adminapipass' => $apiPass,
    ];

    $listUrl = $adminUrl . '?' . http_build_query($listParams);
    $listResult = virtualizorApiPost($listUrl, ['vpsid' => $vpsId]);
    $listData = $listResult['ok'] ? $listResult['data'] : [];

    // Parse status data
    // Virtualizor returns: { "title": "...", "status": { "85": { ... } }, "timenow": ... }
    // The live stats are nested under the "status" key
    $liveStats = [];
    $statusBlock = $statusData;

    // Check if data is nested under "status" key
    if (isset($statusData['status']) && is_array($statusData['status'])) {
        $statusBlock = $statusData['status'];
    }

    if (is_array($statusBlock) && isset($statusBlock[(string) $vpsId])) {
        $liveStats = $statusBlock[(string) $vpsId];
    } elseif (is_array($statusBlock) && isset($statusBlock[$vpsId])) {
        $liveStats = $statusBlock[$vpsId];
    } elseif (is_array($statusBlock)) {
        // Try first element
        foreach ($statusBlock as $k => $v) {
            if (is_array($v) && isset($v['status'])) {
                $liveStats = $v;
                break;
            }
        }
    }

    // Parse VPS info from list data
    // Virtualizor returns: { "title": "...", "vs": { "85": { ... } }, ... }
    // VPS info is nested under the "vs" key
    $vpsInfo = [];
    $vsBlock = $listData;

    // Check if data is nested under "vs" key
    if (isset($listData['vs']) && is_array($listData['vs'])) {
        $vsBlock = $listData['vs'];
    }

    if (is_array($vsBlock) && isset($vsBlock[(string) $vpsId])) {
        $vpsInfo = $vsBlock[(string) $vpsId];
    } elseif (is_array($vsBlock) && isset($vsBlock[$vpsId])) {
        $vpsInfo = $vsBlock[$vpsId];
    } elseif (isset($listData['vs_info']) && is_array($listData['vs_info'])) {
        $vpsInfo = $listData['vs_info'];
    } else {
        // Try to find the VPS in the response (could be nested under any key)
        if (is_array($vsBlock)) {
            foreach ($vsBlock as $key => $value) {
                if (is_array($value) && isset($value['vpsid']) && (int) $value['vpsid'] === $vpsId) {
                    $vpsInfo = $value;
                    break;
                }
            }
        }
    }

    // Parse cached_disk (may be serialized string or array)
    $cachedDisk = [];
    if (!empty($vpsInfo['cached_disk'])) {
        if (is_string($vpsInfo['cached_disk'])) {
            $cachedDisk = @unserialize($vpsInfo['cached_disk']) ?: [];
        } else {
            $cachedDisk = $vpsInfo['cached_disk'];
        }
    }

    // Build normalized response
    $status = 'unknown';
    $statusCode = $liveStats['status'] ?? null;
    if ($statusCode !== null) {
        $statusMap = [0 => 'offline', 1 => 'online', 2 => 'suspended'];
        $status = $statusMap[(int) $statusCode] ?? 'unknown';
    }

    // Disk usage
    $totalDisk = floatval($vpsInfo['space'] ?? $liveStats['disk'] ?? 0);
    $usedDisk  = floatval($liveStats['used_disk'] ?? 0);
    if (!$usedDisk && !empty($cachedDisk['disk']['Use%'])) {
        $usedDisk = round($totalDisk * $cachedDisk['disk']['Use%'] / 100, 2);
    }

    // Bandwidth
    $totalBw = floatval($vpsInfo['bandwidth'] ?? $liveStats['bandwidth'] ?? 0);
    $usedBw  = floatval($vpsInfo['used_bandwidth'] ?? $liveStats['used_bandwidth'] ?? 0);

    // RAM
    $totalRam = floatval($vpsInfo['ram'] ?? $liveStats['ram'] ?? 0);
    $usedRam  = floatval($liveStats['used_ram'] ?? 0);

    // CPU
    $usedCpu  = floatval($liveStats['used_cpu'] ?? 0);
    $cores    = intval($vpsInfo['cores'] ?? 0);

    // IPs
    $ips = [];
    if (!empty($vpsInfo['ips'])) {
        $ips = is_array($vpsInfo['ips']) ? array_values($vpsInfo['ips']) : [$vpsInfo['ips']];
    }
    $ips6 = [];
    if (!empty($vpsInfo['ips6'])) {
        $ips6 = is_array($vpsInfo['ips6']) ? array_values($vpsInfo['ips6']) : [$vpsInfo['ips6']];
    }

    return [
        'result' => 'success',
        'vps' => [
            'vpsid'          => $vpsId,
            'hostname'       => $vpsInfo['hostname'] ?? $service->domain ?? '',
            'os_name'        => $vpsInfo['os_name'] ?? '',
            'os_distro'      => $vpsInfo['os_distro'] ?? '',
            'virt'           => $vpsInfo['virt'] ?? '',
            'status'         => $status,
            'server_name'    => $vpsInfo['server_name'] ?? $server->name ?? '',

            // Resource usage
            'cpu_used'       => $usedCpu,
            'cpu_cores'      => $cores,

            'ram_total'      => $totalRam,    // MB
            'ram_used'       => $usedRam,     // MB

            'disk_total'     => $totalDisk,   // GB
            'disk_used'      => $usedDisk,    // GB

            'bandwidth_total' => $totalBw,    // GB
            'bandwidth_used'  => $usedBw,     // GB

            // Network
            'ips'            => $ips,
            'ips6'           => $ips6,
            'network_speed'  => intval($vpsInfo['network_speed'] ?? 0),

            // VNC
            'vnc_enabled'    => ($vpsInfo['vnc'] ?? '0') === '1',
            'vncport'        => $vpsInfo['vncport'] ?? '',

            // Misc
            'vps_name'       => $vpsInfo['vps_name'] ?? '',
            'suspended'      => ($vpsInfo['suspended'] ?? '0') !== '0',
            'rescue'         => ($vpsInfo['rescue'] ?? '0') !== '0',

            // IO
            'io_read'        => floatval($liveStats['io_read'] ?? 0),
            'io_write'       => floatval($liveStats['io_write'] ?? 0),
            'net_in'         => floatval($liveStats['net_in'] ?? 0),
            'net_out'        => floatval($liveStats['net_out'] ?? 0),
        ],
    ];
}

// ═══════════════════════════════════════════════════════════
// Shared helper: Extract Virtualizor API credentials from WHMCS server record
// Uses multiple approaches to reliably decrypt the server password
// ═══════════════════════════════════════════════════════════
function getVirtualizorCredentials($server)
{
    $apiKey  = trim($server->username ?? '');
    $apiPass = '';
    $method  = 'none';
    $serverId = $server->id ?? 0;

    // ── Method 1: WHMCS Server Model (auto-decrypts password) ──
    // This is the most reliable method — WHMCS 8.x Server model has a
    // password accessor that automatically decrypts the stored value
    if (empty($apiPass) && $serverId && class_exists('\\WHMCS\\Product\\Server\\Server')) {
        try {
            $serverModel = \WHMCS\Product\Server\Server::find($serverId);
            if ($serverModel) {
                $decrypted = $serverModel->password;
                if (!empty($decrypted)) {
                    $apiPass = $decrypted;
                    $method = 'ServerModel';
                }
                // Also grab username from model if we don't have it
                if (empty($apiKey) && !empty($serverModel->username)) {
                    $apiKey = trim($serverModel->username);
                }
            }
        } catch (\Exception $e) {
            // Model not available
        }
    }

    // ── Method 2: localAPI DecryptPassword ──
    if (empty($apiPass) && !empty($server->password)) {
        try {
            $result = localAPI('DecryptPassword', ['password2' => $server->password]);
            if (($result['result'] ?? '') === 'success' && !empty($result['password'])) {
                $apiPass = $result['password'];
                $method = 'localAPI_DecryptPassword';
            }
        } catch (\Exception $e) {
            // Not available
        }
    }

    // ── Method 3: WHMCS decrypt() function ──
    if (empty($apiPass) && !empty($server->password) && function_exists('decrypt')) {
        try {
            $decrypted = decrypt($server->password);
            if (!empty($decrypted) && $decrypted !== $server->password) {
                $apiPass = $decrypted;
                $method = 'decrypt';
            }
        } catch (\Exception $e) {
            // decrypt() failed
        }
    }

    // ── Method 4: WHMCS module params via GetServers ──
    // localAPI 'GetServers' does NOT exist, but we can use the module's
    // own server params approach via GetModuleConfiguration
    if (empty($apiPass) && $serverId) {
        try {
            // Try to get server details through WHMCS's Servers model
            $serverParams = Capsule::table('tblservers')->where('id', $serverId)->first();
            if ($serverParams && !empty($serverParams->password)) {
                // Try WHMCS 8.x Security encryption
                if (class_exists('\\WHMCS\\Security\\Encryption')) {
                    try {
                        $decrypted = \WHMCS\Security\Encryption::decode($serverParams->password);
                        if (!empty($decrypted)) {
                            $apiPass = $decrypted;
                            $method = 'Security_Encryption';
                        }
                    } catch (\Exception $e) {
                        // Not available
                    }
                }
            }
        } catch (\Exception $e) {
            // DB query failed
        }
    }

    // ── Method 5: Raw password (some setups store in plain text) ──
    if (empty($apiPass) && !empty($server->password)) {
        $apiPass = $server->password;
        $method = 'raw_password';
    }

    // ── Fallback: try accesshash for API key ──
    if (empty($apiKey) && !empty($server->accesshash)) {
        $apiKey = trim($server->accesshash);
    }

    return [
        'apiKey'  => $apiKey,
        'apiPass' => trim($apiPass),
        'method'  => $method,
    ];
}


// ═══════════════════════════════════════════════════════════
// Get OS Templates for VPS Rebuild
// Uses the Template Browser API to list available OS templates
// ═══════════════════════════════════════════════════════════
function handleGetOsTemplates($server, $service, $hostname)
{
    $creds = getVirtualizorCredentials($server);
    $apiKey  = $creds['apiKey'];
    $apiPass = $creds['apiPass'];

    if (empty($apiKey) || empty($apiPass)) {
        return ['result' => 'error', 'message' => 'Virtualizor API credentials not configured'];
    }

    // Find VPS ID (reuse same logic)
    $vpsId = findVirtualizorVpsId($service);
    if (!$vpsId) {
        return ['result' => 'error', 'message' => 'VPS ID not found for this service'];
    }

    // Call Virtualizor Admin API to list OS templates
    // The rebuild page endpoint returns available OS templates in the "oslist" key
    // GET act=rebuild&vpsid=VPSID → returns {oslist: {kvm: {group: [{osid, distro, ...}]}}}
    $adminUrl = 'https://' . $hostname . ':4085/index.php';
    $params = [
        'act'          => 'rebuild',
        'api'          => 'json',
        'adminapikey'  => $apiKey,
        'adminapipass' => $apiPass,
        'vpsid'        => $vpsId,
    ];

    $url = $adminUrl . '?' . http_build_query($params);
    $result = virtualizorApiGet($url);

    if (!$result['ok']) {
        return ['result' => 'error', 'message' => 'Failed to fetch OS templates: ' . $result['error']];
    }

    $data = $result['data'];

    // The API returns OS templates organized by virtualization type
    // Structure: { oslist: { kvm: { "Linux": [{osid, distro, ...}], "Windows": [...] } } }
    // Also may return: { ostemplates: {...} } or { oses: {...} }
    $templates = [];

    // Method 1: Parse oslist (most common from rebuild page)
    if (!empty($data['oslist'])) {
        foreach ($data['oslist'] as $virtType => $groups) {
            if (!is_array($groups)) continue;
            foreach ($groups as $groupName => $osList) {
                if (!is_array($osList)) continue;
                foreach ($osList as $os) {
                    if (!is_array($os)) continue;
                    $templates[] = [
                        'osid'    => $os['osid'] ?? $os['id'] ?? 0,
                        'name'    => $os['name'] ?? $os['distro'] ?? 'Unknown',
                        'distro'  => $os['distro'] ?? '',
                        'group'   => $groupName,
                        'virt'    => $virtType,
                    ];
                }
            }
        }
    }

    // Method 2: Parse ostemplates
    if (empty($templates) && !empty($data['ostemplates'])) {
        foreach ($data['ostemplates'] as $osId => $os) {
            if (!is_array($os)) continue;
            $templates[] = [
                'osid'    => $os['osid'] ?? $osId,
                'name'    => $os['name'] ?? $os['distro'] ?? 'Unknown',
                'distro'  => $os['distro'] ?? '',
                'group'   => $os['type'] ?? 'Linux',
                'virt'    => $os['virt'] ?? 'kvm',
            ];
        }
    }

    // Method 3: Parse oses
    if (empty($templates) && !empty($data['oses'])) {
        foreach ($data['oses'] as $osId => $os) {
            if (!is_array($os)) continue;
            $templates[] = [
                'osid'    => $os['osid'] ?? $osId,
                'name'    => $os['name'] ?? $os['distro'] ?? 'Unknown',
                'distro'  => $os['distro'] ?? '',
                'group'   => $os['type'] ?? 'Linux',
                'virt'    => $os['virt'] ?? 'kvm',
            ];
        }
    }

    // Filter out templates with no valid osid
    $templates = array_values(array_filter($templates, fn($t) => !empty($t['osid'])));

    return [
        'result'    => 'success',
        'templates' => $templates,
        'vpsid'     => $vpsId,
        'debug_keys' => array_keys($data),
    ];
}


// ═══════════════════════════════════════════════════════════
// Rebuild VPS — Reinstall OS with new template
// Admin API: POST act=rebuild, vpsid, osid, reos=1, newpass, conf
// ═══════════════════════════════════════════════════════════
function handleRebuildVps($server, $service, $hostname, $osId, $newPass)
{
    $creds = getVirtualizorCredentials($server);
    $apiKey  = $creds['apiKey'];
    $apiPass = $creds['apiPass'];

    if (empty($apiKey) || empty($apiPass)) {
        return ['result' => 'error', 'message' => 'Virtualizor API credentials not configured'];
    }

    // Find VPS ID
    $vpsId = findVirtualizorVpsId($service);
    if (!$vpsId) {
        return ['result' => 'error', 'message' => 'VPS ID not found for this service'];
    }

    // Call Virtualizor Admin API to rebuild VPS
    // Docs: GET query has act=rebuild, auth params; POST body has vpsid, osid, reos, newpass, conf
    $adminUrl = 'https://' . $hostname . ':4085/index.php';
    $queryParams = [
        'act'          => 'rebuild',
        'api'          => 'json',
        'adminapikey'  => $apiKey,
        'adminapipass' => $apiPass,
    ];

    $url = $adminUrl . '?' . http_build_query($queryParams);

    $postData = [
        'vpsid'   => $vpsId,
        'osid'    => $osId,
        'reos'    => 1,
        'newpass' => $newPass,
        'conf'    => $newPass,
    ];

    $result = virtualizorApiPost($url, $postData);

    if (!$result['ok']) {
        return ['result' => 'error', 'message' => 'Rebuild API failed: ' . $result['error']];
    }

    $data = $result['data'];

    // Check for errors
    if (!empty($data['error'])) {
        $errorMsg = is_array($data['error']) ? implode(', ', array_values($data['error'])) : $data['error'];
        return ['result' => 'error', 'message' => 'Rebuild failed: ' . $errorMsg];
    }

    // Check for success
    if (!empty($data['done'])) {
        $osName = '';
        if (!empty($data['vps']['os_name'])) {
            $osName = $data['vps']['os_name'];
        } elseif (!empty($data['vpses'][$vpsId]['os_name'])) {
            $osName = $data['vpses'][$vpsId]['os_name'];
        }

        return [
            'result'  => 'success',
            'message' => 'VPS is being rebuilt' . ($osName ? ' with ' . $osName : '') . '. This may take a few minutes.',
            'os_name' => $osName,
        ];
    }

    return [
        'result'  => 'error',
        'message' => 'Rebuild response unclear. Please check Virtualizor panel for status.',
        'debug'   => array_keys($data),
    ];
}


// ═══════════════════════════════════════════════════════════
// Helper: Find Virtualizor VPS ID from WHMCS service
// (Extracted from repeated code in handlers)
// ═══════════════════════════════════════════════════════════
function findVirtualizorVpsId($service)
{
    $vpsId = 0;

    $customFields = Capsule::table('tblcustomfields')
        ->where('relid', $service->packageid)
        ->where('type', 'product')
        ->get();

    foreach ($customFields as $cf) {
        $fieldName = strtolower($cf->fieldname);
        if (in_array($fieldName, ['vpsid', 'vps id', 'vserverid', 'vps_id'])) {
            $cfVal = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $cf->id)
                ->where('relid', $service->id)
                ->value('value');
            if (!empty($cfVal)) {
                return (int) $cfVal;
            }
        }
    }

    if (!empty($service->domain) && is_numeric($service->domain)) {
        return (int) $service->domain;
    }

    foreach ($customFields as $cf) {
        $fieldName = strtolower($cf->fieldname);
        if (str_contains($fieldName, 'server') || str_contains($fieldName, 'vps')) {
            $cfVal = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $cf->id)
                ->where('relid', $service->id)
                ->value('value');
            if (!empty($cfVal) && is_numeric($cfVal)) {
                return (int) $cfVal;
            }
        }
    }

    return 0;
}


// Helper: GET request to Virtualizor API
// Returns ['ok' => true, 'data' => [...]] on success
// Returns ['ok' => false, 'error' => '...', 'http_code' => N] on failure
function virtualizorApiGet($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curlError, 'http_code' => 0];
    }

    if ($httpCode === 302 || $httpCode === 301) {
        return ['ok' => false, 'error' => 'API returned redirect (HTTP ' . $httpCode . ') — authentication failed', 'http_code' => $httpCode, 'redirect_to' => $redirectUrl ?: null, 'body_preview' => substr($response, 0, 200)];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'API returned HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        return ['ok' => false, 'error' => 'Invalid JSON response (HTTP ' . $httpCode . ')', 'http_code' => $httpCode, 'body' => substr($response, 0, 300)];
    }

    return ['ok' => true, 'data' => $data, 'http_code' => $httpCode];
}

// Helper: POST request to Virtualizor API
// Returns ['ok' => true, 'data' => [...]] on success
// Returns ['ok' => false, 'error' => '...', 'http_code' => N] on failure
function virtualizorApiPost($url, $postData = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curlError, 'http_code' => 0];
    }

    if ($httpCode === 302 || $httpCode === 301) {
        return ['ok' => false, 'error' => 'API returned redirect (HTTP ' . $httpCode . ') — authentication failed', 'http_code' => $httpCode, 'redirect_to' => $redirectUrl ?: null];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'API returned HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        return ['ok' => false, 'error' => 'Invalid JSON response (HTTP ' . $httpCode . ')', 'http_code' => $httpCode, 'body' => substr($response, 0, 300)];
    }

    return ['ok' => true, 'data' => $data, 'http_code' => $httpCode];
}
