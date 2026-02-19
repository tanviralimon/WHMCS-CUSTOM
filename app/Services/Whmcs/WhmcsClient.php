<?php

namespace App\Services\Whmcs;

use App\Exceptions\WhmcsApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level HTTP wrapper for WHMCS API.
 * Handles authentication, retries, timeouts, SSL verification, and error normalization.
 */
class WhmcsClient
{
    protected string $baseUrl;
    protected string $identifier;
    protected string $secret;
    protected int $timeout;
    protected bool $verifySSL;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whmcs.base_url'), '/');
        $this->identifier = config('whmcs.api_identifier');
        $this->secret = config('whmcs.api_secret');
        $this->timeout = (int) config('whmcs.api_timeout', 10);
        $this->verifySSL = (bool) config('whmcs.verify_ssl', true);
    }

    /**
     * Send a POST request to the WHMCS API.
     *
     * @param string $action  WHMCS API action name.
     * @param array  $params  Additional parameters (never include identifier/secret here).
     * @return array  Decoded JSON response.
     *
     * @throws WhmcsApiException
     */
    public function call(string $action, array $params = [], ?int $timeout = null): array
    {
        $payload = array_merge([
            'action'       => $action,
            'identifier'   => $this->identifier,
            'secret'       => $this->secret,
            'responsetype' => 'json',
        ], $params);

        try {
            $response = Http::timeout($timeout ?? $this->timeout)
                ->withOptions(['verify' => $this->verifySSL])
                ->retry(2, 500, function ($exception) {
                    // Only retry on connection timeouts, not on 4xx/5xx
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                })
                ->asForm()
                ->post("{$this->baseUrl}/includes/api.php", $payload);

            $data = $response->json();

            if (!$data || !is_array($data)) {
                Log::error("WHMCS API: Non-JSON response", [
                    'action' => $action,
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                throw new WhmcsApiException(
                    'Invalid response from billing system',
                    $action,
                    $this->redactParams($params)
                );
            }

            if (($data['result'] ?? '') === 'error') {
                $rawMsg = $data['message'] ?? 'Unknown WHMCS error';
                Log::warning("WHMCS API error", [
                    'action'  => $action,
                    'message' => $rawMsg,
                ]);
                throw new WhmcsApiException(
                    WhmcsApiException::friendlyMessage($rawMsg, $action),
                    $action,
                    $this->redactParams($params)
                );
            }

            return $data;
        } catch (WhmcsApiException $e) {
            throw $e; // Re-throw domain exceptions
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("WHMCS API connection failed", ['action' => $action, 'error' => $e->getMessage()]);
            throw new WhmcsApiException(
                'Unable to connect to billing system. Please try again later.',
                $action,
                $this->redactParams($params)
            );
        } catch (\Exception $e) {
            Log::error("WHMCS API unexpected error", ['action' => $action, 'error' => $e->getMessage()]);
            throw new WhmcsApiException(
                'An unexpected error occurred. Please try again later.',
                $action,
                $this->redactParams($params)
            );
        }
    }

    /**
     * Non-throwing version — returns ['result' => 'error'] on failure.
     */
    public function callSafe(string $action, array $params = []): array
    {
        try {
            return $this->call($action, $params);
        } catch (WhmcsApiException $e) {
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Call the standalone DNS proxy on the WHMCS server.
     * This bypasses the standard API and calls registrar module functions directly.
     */
    public function callDnsProxy(string $action, array $params = []): array
    {
        $payload = array_merge([
            'action'     => $action,
            'identifier' => $this->identifier,
            'secret'     => $this->secret,
        ], $params);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => $this->verifySSL])
                ->asForm()
                ->post("{$this->baseUrl}/orcus_dns.php", $payload);

            $data = $response->json();

            if (!$data || !is_array($data)) {
                Log::error('WHMCS DNS Proxy: Non-JSON response', [
                    'action' => $action,
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                throw new WhmcsApiException('Invalid response from DNS service', $action, []);
            }

            if (($data['result'] ?? '') === 'error') {
                throw new WhmcsApiException($data['message'] ?? 'DNS operation failed', $action, []);
            }

            return $data;
        } catch (WhmcsApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WHMCS DNS Proxy error', ['action' => $action, 'error' => $e->getMessage()]);
            throw new WhmcsApiException('DNS service unavailable: ' . $e->getMessage(), $action, []);
        }
    }

    /**
     * Non-throwing version of callDnsProxy.
     */
    public function callDnsProxySafe(string $action, array $params = []): array
    {
        try {
            return $this->callDnsProxy($action, $params);
        } catch (WhmcsApiException $e) {
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Call the SSO proxy on the WHMCS server.
     * Generates direct SSO login URLs for control panels (SPanel, cPanel, etc.)
     */
    /** Actions that call the Virtualizor API (or other slow external APIs) internally. */
    private const SSO_SLOW_ACTIONS = [
        'GetVpsStats', 'VpsAction', 'RebuildVps', 'ChangeHostname',
        'GetIPs', 'GetSSH', 'GetSshKeys', 'AddSshKey', 'RemoveSshKey',
        'GetVnc', 'ChangeVncPassword', 'TestVirtApi',
    ];

    public function callSsoProxy(string $action, array $params = []): array
    {
        $payload = array_merge([
            'action'     => $action,
            'identifier' => $this->identifier,
            'secret'     => $this->secret,
        ], $params);

        // Virtualizor-backed actions make up to two outbound API calls;
        // give them 45 s so they don't time out before orcus_sso.php replies.
        $timeout = in_array($action, self::SSO_SLOW_ACTIONS) ? 45 : $this->timeout;

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['verify' => $this->verifySSL])
                ->asForm()
                ->post("{$this->baseUrl}/orcus_sso.php", $payload);

            $data = $response->json();

            if (!$data || !is_array($data)) {
                Log::error('WHMCS SSO Proxy: Non-JSON response', [
                    'action' => $action,
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                throw new WhmcsApiException('Invalid response from SSO service', $action, []);
            }

            if (($data['result'] ?? '') === 'error') {
                throw new WhmcsApiException($data['message'] ?? 'SSO operation failed', $action, []);
            }

            return $data;
        } catch (WhmcsApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WHMCS SSO Proxy error', ['action' => $action, 'error' => $e->getMessage()]);
            throw new WhmcsApiException('SSO service unavailable: ' . $e->getMessage(), $action, []);
        }
    }

    /**
     * Non-throwing version of callSsoProxy.
     */
    public function callSsoProxySafe(string $action, array $params = []): array
    {
        try {
            return $this->callSsoProxy($action, $params);
        } catch (WhmcsApiException $e) {
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Remove sensitive fields from params before logging.
     */
    protected function redactParams(array $params): array
    {
        $redacted = $params;
        foreach (['password', 'password2', 'cardnum', 'cardcvv', 'identifier', 'secret'] as $key) {
            if (isset($redacted[$key])) {
                $redacted[$key] = '***REDACTED***';
            }
        }
        return $redacted;
    }
}
