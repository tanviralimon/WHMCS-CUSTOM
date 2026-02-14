<?php
/**
 * GitHub Webhook Receiver
 * 
 * Place this file on your server at:
 *   /home/USER/htdocs/orcus.one/public/deploy-webhook.php
 * 
 * Then add a GitHub webhook:
 *   URL: https://orcus.one/deploy-webhook.php
 *   Secret: (set the same secret below)
 *   Events: Just the push event
 */

// ─── Configuration ───────────────────────────────────
$secret = getenv('DEPLOY_WEBHOOK_SECRET') ?: 'CHANGE_THIS_TO_A_RANDOM_STRING';
$deployScript = dirname(__DIR__) . '/../scripts/webhook-deploy.sh';
$logFile = dirname(__DIR__) . '/../storage/logs/webhook.log';

// ─── Verify GitHub signature ─────────────────────────
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (empty($signature)) {
    http_response_code(403);
    exit('No signature');
}

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// ─── Only deploy on push to main ─────────────────────
$data = json_decode($payload, true);
$ref = $data['ref'] ?? '';

if ($ref !== 'refs/heads/main') {
    echo 'Not main branch, skipping';
    exit;
}

// ─── Run deploy script ──────────────────────────────
$output = shell_exec("bash $deployScript 2>&1");

// Log it
file_put_contents($logFile, date('Y-m-d H:i:s') . "\n" . $output . "\n\n", FILE_APPEND);

echo "Deployed successfully\n";
echo $output;
