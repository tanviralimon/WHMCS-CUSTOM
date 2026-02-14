<?php

namespace App\Http\Controllers;

use App\Services\WhmcsApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    protected WhmcsApiService $whmcs;

    public function __construct(WhmcsApiService $whmcs)
    {
        $this->whmcs = $whmcs;
    }

    public function index(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page = max(0, ((int) $request->get('page', 1)) - 1);
        $status = $request->get('status', '');

        $result = $this->whmcs->getTickets($clientId, $status, $page * 25, 25);

        return Inertia::render('Tickets/Index', [
            'tickets' => $result['tickets']['ticket'] ?? [],
            'total' => (int) ($result['totalresults'] ?? 0),
            'page' => $page + 1,
            'status' => $status,
        ]);
    }

    public function show(int $id)
    {
        $result = $this->whmcs->getTicket($id);

        if (($result['result'] ?? '') !== 'success') {
            abort(404);
        }

        return Inertia::render('Tickets/Show', [
            'ticket' => $result,
        ]);
    }

    public function create(Request $request)
    {
        $departments = $this->whmcs->getSupportDepartments();

        return Inertia::render('Tickets/Create', [
            'departments' => $departments['departments']['department'] ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'deptid' => 'required|integer',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:Low,Medium,High',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        $result = $this->whmcs->openTicket(
            $clientId,
            $request->input('deptid'),
            $request->input('subject'),
            $request->input('message'),
            $request->input('priority', 'Medium')
        );

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['message' => $result['message'] ?? 'Failed to open ticket']);
        }

        return redirect()->route('tickets.show', $result['id'])
            ->with('success', 'Ticket opened successfully');
    }

    public function reply(Request $request, int $id)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        $result = $this->whmcs->addTicketReply($id, $clientId, $request->input('message'));

        if (($result['result'] ?? '') !== 'success') {
            return back()->withErrors(['message' => $result['message'] ?? 'Failed to send reply']);
        }

        return back()->with('success', 'Reply sent successfully');
    }
}
