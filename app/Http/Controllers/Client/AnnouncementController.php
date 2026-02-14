<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index(Request $request)
    {
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 10;

        $result = $this->whmcs->getAnnouncements(($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Announcements/Index', [
            'announcements' => $result['announcements']['announcement'] ?? [],
            'total'         => (int) ($result['totalresults'] ?? 0),
            'page'          => $page,
            'perPage'       => $perPage,
        ]);
    }

    public function show(int $id)
    {
        $result       = $this->whmcs->getAnnouncement($id);
        $announcement = ($result['announcements']['announcement'] ?? [null])[0] ?? null;

        if (!$announcement) {
            abort(404);
        }

        return Inertia::render('Client/Announcements/Show', [
            'announcement' => $announcement,
        ]);
    }
}
