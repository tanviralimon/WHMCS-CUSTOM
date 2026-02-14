<?php

namespace App\Http\Middleware;

use App\Services\Whmcs\WhmcsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameterized middleware to verify WHMCS resource ownership.
 *
 * Usage in routes:
 *   ->middleware('whmcs.own:invoice,id')
 *   ->middleware('whmcs.own:service,id')
 *   ->middleware('whmcs.own:domain,id')
 *   ->middleware('whmcs.own:ticket,id')
 *   ->middleware('whmcs.own:quote,id')
 */
class EnsureWhmcsOwnership
{
    protected WhmcsService $whmcs;

    public function __construct(WhmcsService $whmcs)
    {
        $this->whmcs = $whmcs;
    }

    public function handle(Request $request, Closure $next, string $type, string $routeParam = 'id'): Response
    {
        $clientId = $request->user()?->whmcs_client_id;
        if (!$clientId) {
            abort(403, 'No WHMCS account linked.');
        }

        $resourceId = (int) $request->route($routeParam);
        if (!$resourceId) {
            abort(404);
        }

        $cacheKey = "whmcs_own.{$type}.{$resourceId}.{$clientId}";

        $owned = Cache::remember($cacheKey, 60, function () use ($type, $resourceId, $clientId) {
            return $this->checkOwnership($type, $resourceId, $clientId);
        });

        if (!$owned) {
            Cache::forget($cacheKey); // Don't cache negative results
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }

    protected function checkOwnership(string $type, int $resourceId, int $clientId): bool
    {
        return match ($type) {
            'invoice' => $this->checkInvoice($resourceId, $clientId),
            'service' => $this->checkService($resourceId, $clientId),
            'domain'  => $this->checkDomain($resourceId, $clientId),
            'ticket'  => $this->checkTicket($resourceId, $clientId),
            'quote'   => $this->checkQuote($resourceId, $clientId),
            default   => false,
        };
    }

    protected function checkInvoice(int $invoiceId, int $clientId): bool
    {
        $data = $this->whmcs->getInvoice($invoiceId);
        return ($data['result'] ?? '') === 'success'
            && (int) ($data['userid'] ?? 0) === $clientId;
    }

    protected function checkService(int $serviceId, int $clientId): bool
    {
        $data = $this->whmcs->getClientProduct($clientId, $serviceId);
        $products = $data['products']['product'] ?? [];
        return count($products) > 0
            && (int) ($products[0]['clientid'] ?? 0) === $clientId;
    }

    protected function checkDomain(int $domainId, int $clientId): bool
    {
        $data = $this->whmcs->getClientDomain($clientId, $domainId);
        $domains = $data['domains']['domain'] ?? [];
        return count($domains) > 0
            && (int) ($domains[0]['userid'] ?? $domains[0]['clientid'] ?? 0) === $clientId;
    }

    protected function checkTicket(int $ticketId, int $clientId): bool
    {
        $data = $this->whmcs->getTicket($ticketId);
        return ($data['result'] ?? '') === 'success'
            && (int) ($data['userid'] ?? $data['clientid'] ?? 0) === $clientId;
    }

    protected function checkQuote(int $quoteId, int $clientId): bool
    {
        $data = $this->whmcs->getQuote($quoteId);
        $quotes = $data['quotes']['quote'] ?? [];
        if (count($quotes) > 0) {
            return (int) ($quotes[0]['userid'] ?? 0) === $clientId;
        }
        return false;
    }
}
