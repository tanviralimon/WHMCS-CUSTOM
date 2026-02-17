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

        return Inertia::render('Client/Tickets/Show', [
            'ticket' => $result,
        ]);
    }

    public function create()
    {
        $departments = $this->whmcs->getSupportDepartments();

        return Inertia::render('Client/Tickets/Create', [
            'departments' => $departments['departments']['department'] ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'deptid'   => 'required|integer',
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string|max:10000',
            'priority' => 'required|in:Low,Medium,High',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        $result = $this->whmcs->openTicket(
            $clientId,
            $request->deptid,
            $request->subject,
            $request->message,
            $request->priority
        );

        return redirect()
            ->route('client.tickets.show', $result['id'] ?? 0)
            ->with('success', 'Ticket opened successfully.');
    }

    public function reply(Request $request, int $id)
    {
        $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $clientId = $request->user()->whmcs_client_id;
        $this->whmcs->addTicketReply($id, $clientId, $request->message);

        return back()->with('success', 'Reply sent successfully.');
    }

    public function close(int $id)
    {
        $this->whmcs->closeTicket($id);
        return back()->with('success', 'Ticket closed successfully.');
    }
}
