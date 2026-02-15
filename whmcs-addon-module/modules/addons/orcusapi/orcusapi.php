<?php
/**
 * Orcus API – WHMCS Addon Module
 *
 * Exposes custom API actions through the standard WHMCS API endpoint
 * (/includes/api.php) that are NOT available in the default WHMCS API.
 *
 * Custom Actions Provided:
 *   OrcusGetDNS      — Fetch DNS records for a domain via registrar module
 *   OrcusSaveDNS     — Save DNS records for a domain via registrar module
 *   OrcusGetStats    — Get aggregated client dashboard statistics
 *   OrcusGetServiceInfo — Get extended service/product info with server details
 *   OrcusGetEmailForwarding  — Get email forwarding rules for a domain
 *   OrcusSaveEmailForwarding — Save email forwarding rules for a domain
 *
 * Installation:
 *   1. Upload this folder to: modules/addons/orcusapi/
 *   2. Go to WHMCS Admin → Setup → Addon Modules
 *   3. Activate "Orcus Portal API"
 *   4. Configure access control (grant to "Full Administrator")
 *
 * @package    OrcusAPI
 * @author     Orcus Technologies
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Module configuration.
 */
function orcusapi_config()
{
    return [
        'name'        => 'Orcus Portal API',
        'description' => 'Provides custom API endpoints for the Orcus client portal (DNS management, extended stats, service info, email forwarding).',
        'author'      => 'Orcus Technologies',
        'language'    => 'english',
        'version'     => '1.0.0',
        'fields'      => [
            'apiSecretKey' => [
                'FriendlyName' => 'API Secret Key',
                'Type'         => 'text',
                'Size'         => '40',
                'Default'      => bin2hex(random_bytes(16)),
                'Description'  => 'Optional extra security key for portal requests. Leave default or change.',
            ],
        ],
    ];
}

/**
 * Activate — nothing to do, no custom tables needed.
 */
function orcusapi_activate()
{
    return ['status' => 'success', 'description' => 'Orcus Portal API activated. Custom API actions are now available.'];
}

/**
 * Deactivate.
 */
function orcusapi_deactivate()
{
    return ['status' => 'success', 'description' => 'Orcus Portal API deactivated.'];
}

/**
 * Admin area output (simple status page).
 */
function orcusapi_output($vars)
{
    $version = $vars['version'];
    echo <<<HTML
<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">Orcus Portal API v{$version}</h3></div>
    <div class="panel-body">
        <p>This module provides the following custom API actions for the Orcus client portal:</p>
        <table class="table table-striped">
            <thead><tr><th>Action</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td><code>OrcusGetDNS</code></td><td>Get DNS records for a domain (via registrar module)</td></tr>
                <tr><td><code>OrcusSaveDNS</code></td><td>Save DNS records for a domain (via registrar module)</td></tr>
                <tr><td><code>OrcusGetStats</code></td><td>Get aggregated dashboard statistics for a client</td></tr>
                <tr><td><code>OrcusGetServiceInfo</code></td><td>Get extended service info with server details</td></tr>
                <tr><td><code>OrcusGetEmailForwarding</code></td><td>Get email forwarding rules for a domain</td></tr>
                <tr><td><code>OrcusSaveEmailForwarding</code></td><td>Save email forwarding rules for a domain</td></tr>
            </tbody>
        </table>
        <div class="alert alert-info">
            <strong>Usage:</strong> Call these actions via the standard WHMCS API endpoint
            (<code>/includes/api.php</code>) using your API credentials, just like any built-in action.
        </div>
    </div>
</div>
HTML;
}
