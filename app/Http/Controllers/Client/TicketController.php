<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $status   = $request->get('status', '');
        $perPage  = 25;

        $result = $this->whmcs->getTickets($clientId, $status, ($page - 1) * $perPage, $perPage);

        $tickets = $result['tickets']['ticket'] ?? [];

        // Sort: open/customer-reply first, then by most recent reply
        if (!$status) {
            $priority = ['Open' => 0, 'Customer-Reply' => 1, 'Answered' => 2, 'On Hold' => 3, 'In Progress' => 4];
            usort($tickets, function ($a, $b) use ($priority) {
                $pa = $priority[$a['status']] ?? 8;
                $pb = $priority[$b['status']] ?? 8;
                if ($pa !== $pb) return $pa - $pb;
                // Within same group, newest last-reply first
                return strtotime($b['lastreply'] ?? $b['date'] ?? '0') - strtotime($a['lastreply'] ?? $a['date'] ?? '0');
            });
        } else {
            // Single status: newest first
            usort($tickets, fn($a, $b) => strtotime($b['lastreply'] ?? $b['date'] ?? '0') - strtotime($a['lastreply'] ?? $a['date'] ?? '0'));
        }

        return Inertia::render('Client/Tickets/Index', [
            'tickets' => $tickets,
            'total'   => (int) ($result['totalresults'] ?? 0),
            'page'    => $page,
            'perPage' => $perPage,
            'status'  => $status,
        ]);
    }

    public function show(int $id)
    {
        $result = $this->whmcs->getTicket($id);

        if (($result['result'] ?? '') !== 'success') {
            abort(404);
        }

        // WHMCS GetTicket returns the original message in top-level fields
        // AND often duplicates it as the first entry in the replies array.
        // Strip the duplicate so the front-end doesn't show it twice.
        $origMsg  = $this->normaliseForCompare($result['message'] ?? '');
        $origDate = trim($result['date'] ?? '');

        if (!empty($result['replies']['reply']) && is_array($result['replies']['reply'])) {
            $result['replies']['reply'] = array_values(
                array_filter($result['replies']['reply'], function ($r) use ($origMsg, $origDate) {
                    $rMsg  = $this->normaliseForCompare($r['message'] ?? '');
                    $rDate = trim($r['date'] ?? '');
                    // Skip if same date, same content, and not from admin
                    if ($rDate === $origDate && empty($r['admin']) && $rMsg === $origMsg) {
                        return false;
                    }
                    return true;
                })
            );
        }

        return Inertia::render('Client/Tickets/Show', [
            'ticket' => $result,
        ]);
    }

    /**
     * Strip HTML, decode entities, collapse whitespace for message comparison.
     */
    private function normaliseForCompare(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public function create(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $departments = $this->whmcs->getSupportDepartments();

        // Fetch client's active products/services and domains for "Related Service" selector
        $productsResult = $this->whmcs->getClientsProducts($clientId, 0, 250);
        $domainsResult  = $this->whmcs->getClientsDomains($clientId, 0, 250);

        $services = [];

        // Products / Hosting accounts
        $products = $productsResult['products']['product'] ?? [];
        foreach ($products as $p) {
            $label = $p['name'] ?? $p['groupname'] ?? 'Service';
            if (!empty($p['domain'])) {
                $label .= ' - ' . $p['domain'];
            }
            $services[] = [
                'id'     => 'S' . $p['id'],
                'type'   => 'product',
                'label'  => $label,
                'status' => $p['status'] ?? '',
                'domain' => $p['domain'] ?? '',
            ];
        }

        // Domains
        $domains = $domainsResult['domains']['domain'] ?? [];
        foreach ($domains as $d) {
            $services[] = [
                'id'     => 'D' . $d['id'],
                'type'   => 'domain',
                'label'  => $d['domainname'] ?? $d['domain'] ?? 'Domain',
                'status' => $d['status'] ?? '',
                'domain' => $d['domainname'] ?? $d['domain'] ?? '',
            ];
        }

        return Inertia::render('Client/Tickets/Create', [
            'departments' => $departments['departments']['department'] ?? [],
            'services'    => $services,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'deptid'        => 'required|integer',
            'subject'       => 'required|string|max:255',
            'message'       => 'required|string|max:10000',
            'priority'      => 'required|in:Low,Medium,High',
            'service_id'    => 'required|string|max:20',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:2048|mimes:jpg,jpeg,gif,png,txt,pdf,zip,doc,docx',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        // Build message with credentials if provided
        $message = $request->message;
        if ($request->credentials) {
            $message .= $this->buildCredentialsBlock($request->credentials);
        }

        // Process attachments for WHMCS API (base64-encoded)
        $attachments = $this->processAttachments($request);

        // Parse related service (S123 = product, D123 = domain)
        $serviceId = null;
        $domainId  = null;
        if ($request->service_id) {
            $prefix = substr($request->service_id, 0, 1);
            $id     = (int) substr($request->service_id, 1);
            if ($prefix === 'S') {
                $serviceId = $id;
            } elseif ($prefix === 'D') {
                $domainId = $id;
            }
        }

        $result = $this->whmcs->openTicket(
            $clientId,
            $request->deptid,
            $request->subject,
            $message,
            $request->priority,
            $attachments,
            $serviceId,
            $domainId
        );

        return redirect()
            ->route('client.tickets.show', $result['id'] ?? 0)
            ->with('success', 'Ticket opened successfully.');
    }

    public function reply(Request $request, int $id)
    {
        $request->validate([
            'message'       => 'required|string|max:10000',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:2048|mimes:jpg,jpeg,gif,png,txt,pdf,zip,doc,docx',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        // Build message with credentials if provided
        $message = $request->message;
        if ($request->credentials) {
            $message .= $this->buildCredentialsBlock($request->credentials);
        }

        // Process attachments
        $attachments = $this->processAttachments($request);

        $this->whmcs->addTicketReply($id, $clientId, $message, $attachments);

        $msg = 'Reply sent successfully.';
        if (!empty($attachments)) {
            $msg .= ' (Attachments may take a moment to appear.)';
        }
        return back()->with('success', $msg);
    }

    public function close(int $id)
    {
        $result = $this->whmcs->closeTicket($id);

        if (($result['result'] ?? '') === 'error') {
            return back()->with('error', $result['message'] ?? 'Failed to close ticket.');
        }

        return redirect()->route('client.tickets.show', $id)
            ->with('success', 'Ticket closed successfully.');
    }

    /**
     * Process uploaded attachments into WHMCS-compatible array.
     * WHMCS expects: [['name' => 'file.ext', 'data' => base64_encode(filedata)], ...]
     */
    private function processAttachments(Request $request): array
    {
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'data' => base64_encode(file_get_contents($file->getRealPath())),
                ];
            }
        }
        return $attachments;
    }

    /**
     * Build formatted credentials blocks from JSON string of multiple credential sets.
     */
    private function buildCredentialsBlock(string $credentialsJson): string
    {
        $credentials = json_decode($credentialsJson, true);
        if (!is_array($credentials) || empty($credentials)) {
            return '';
        }

        $output = '';
        foreach ($credentials as $i => $cred) {
            $type = $cred['type'] ?? 'Credentials';
            $num  = count($credentials) > 1 ? ' #' . ($i + 1) : '';
            $parts = ["\n\n───── {$type} Access{$num} ─────"];
            if (!empty($cred['host']))     $parts[] = "Host / IP: {$cred['host']}";
            if (!empty($cred['port']))     $parts[] = "Port: {$cred['port']}";
            if (!empty($cred['username'])) $parts[] = "Username: {$cred['username']}";
            if (!empty($cred['password'])) $parts[] = "Password: {$cred['password']}";
            if (!empty($cred['notes']))    $parts[] = "Notes: {$cred['notes']}";
            $parts[] = "──────────────────────────";
            $output .= implode("\n", $parts);
        }
        return $output;
    }
}
