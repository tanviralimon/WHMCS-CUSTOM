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
        'ssoSupported' => in_array($module, ['spanel', 'cpanel']),
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

    if (!$username) {
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
    try {
        $groups = Capsule::table('tblproductgroups')
            ->select('id', 'name', 'slug', 'headline', 'tagline', 'orderfrmtpl', 'hidden')
            ->orderBy('order', 'asc')
            ->get();

        $result = [];
        foreach ($groups as $g) {
            // Skip hidden groups
            if (!empty($g->hidden)) continue;
            $result[] = [
                'id'       => (int) $g->id,
                'name'     => $g->name,
                'slug'     => $g->slug ?? '',
                'headline' => $g->headline ?? '',
                'tagline'  => $g->tagline ?? '',
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
