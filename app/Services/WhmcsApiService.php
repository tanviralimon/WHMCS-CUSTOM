<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhmcsApiService
{
    protected string $baseUrl;
    protected string $identifier;
    protected string $secret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whmcs.base_url'), '/');
        $this->identifier = config('whmcs.api_identifier');
        $this->secret = config('whmcs.api_secret');
        $this->timeout = (int) config('whmcs.api_timeout', 10);
    }

    /**
     * Make a WHMCS API call
     */
    public function call(string $action, array $params = []): array
    {
        $payload = array_merge([
            'action' => $action,
            'identifier' => $this->identifier,
            'secret' => $this->secret,
            'responsetype' => 'json',
        ], $params);

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post("{$this->baseUrl}/includes/api.php", $payload);

            $data = $response->json();

            if (!$data) {
                Log::error("WHMCS API: Empty response for action {$action}");
                return ['result' => 'error', 'message' => 'Empty response from WHMCS'];
            }

            return $data;
        } catch (\Exception $e) {
            Log::error("WHMCS API error: {$e->getMessage()}", [
                'action' => $action,
            ]);
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ── Auth ───────────────────────────────────────────────

    public function validateLogin(string $email, string $password): array
    {
        return $this->call('ValidateLogin', [
            'email' => $email,
            'password2' => $password,
        ]);
    }

    public function addClient(array $data): array
    {
        return $this->call('AddClient', $data);
    }

    // ── Client Details ─────────────────────────────────────

    public function getClientsDetails(int $clientId): array
    {
        return $this->call('GetClientsDetails', [
            'clientid' => $clientId,
            'stats' => true,
        ]);
    }

    public function updateClient(int $clientId, array $data): array
    {
        return $this->call('UpdateClient', array_merge(['clientid' => $clientId], $data));
    }

    // ── Services / Products ────────────────────────────────

    public function getClientsProducts(int $clientId, int $limitStart = 0, int $limitNum = 25): array
    {
        return $this->call('GetClientsProducts', [
            'clientid' => $clientId,
            'limitstart' => $limitStart,
            'limitnum' => $limitNum,
        ]);
    }

    public function getProduct(int $clientId, int $serviceId): array
    {
        return $this->call('GetClientsProducts', [
            'clientid' => $clientId,
            'serviceid' => $serviceId,
        ]);
    }

    // ── Invoices ───────────────────────────────────────────

    public function getInvoices(int $clientId, string $status = '', int $limitStart = 0, int $limitNum = 25): array
    {
        $params = [
            'userid' => $clientId,
            'limitstart' => $limitStart,
            'limitnum' => $limitNum,
        ];

        if ($status) {
            $params['status'] = $status;
        }

        return $this->call('GetInvoices', $params);
    }

    public function getInvoice(int $invoiceId): array
    {
        return $this->call('GetInvoice', ['invoiceid' => $invoiceId]);
    }

    // ── Tickets ────────────────────────────────────────────

    public function getTickets(int $clientId, string $status = '', int $limitStart = 0, int $limitNum = 25): array
    {
        $params = [
            'clientid' => $clientId,
            'limitstart' => $limitStart,
            'limitnum' => $limitNum,
        ];

        if ($status) {
            $params['status'] = $status;
        }

        return $this->call('GetTickets', $params);
    }

    public function getTicket(int $ticketId): array
    {
        return $this->call('GetTicket', ['ticketid' => $ticketId]);
    }

    public function openTicket(int $clientId, int $deptId, string $subject, string $message, string $priority = 'Medium'): array
    {
        return $this->call('OpenTicket', [
            'clientid' => $clientId,
            'deptid' => $deptId,
            'subject' => $subject,
            'message' => $message,
            'priority' => $priority,
        ]);
    }

    public function addTicketReply(int $ticketId, int $clientId, string $message): array
    {
        return $this->call('AddTicketReply', [
            'ticketid' => $ticketId,
            'clientid' => $clientId,
            'message' => $message,
        ]);
    }

    public function getSupportDepartments(): array
    {
        return $this->call('GetSupportDepartments');
    }
}
