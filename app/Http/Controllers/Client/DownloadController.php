<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DownloadController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index(Request $request)
    {
        $catId   = (int) $request->get('category', 0);
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 25;

        $result = $this->whmcs->getDownloads($catId, ($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Downloads/Index', [
            'downloads' => $result['downloads']['download'] ?? [],
            'total'     => (int) ($result['totalresults'] ?? 0),
            'page'      => $page,
            'perPage'   => $perPage,
            'category'  => $catId,
        ]);
    }
}
