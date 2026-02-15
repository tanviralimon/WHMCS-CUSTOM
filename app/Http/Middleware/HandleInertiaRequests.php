<?php

namespace App\Http\Middleware;

use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'features' => config('client-features'),
            'whmcsUrl' => rtrim(config('whmcs.base_url'), '/'),
            'currencies' => fn () => $this->getCurrencies(),
            'activeCurrencyId' => fn () => (int) ($request->session()->get('currency_id', 1)),
        ];
    }

    private function getCurrencies(): array
    {
        try {
            $whmcs = app(WhmcsService::class);
            $result = $whmcs->getCurrencies();
            return $result['currencies']['currency'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
