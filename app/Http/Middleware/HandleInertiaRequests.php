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
                'eppCode' => fn () => $request->session()->get('eppCode'),
            ],
            'features' => config('client-features'),
            'whmcsUrl' => rtrim(config('whmcs.base_url'), '/'),
            'currencies' => fn () => $this->getCurrencies(),
            'activeCurrencyId' => fn () => $this->getActiveCurrencyId($request),
        ];
    }

    /**
     * Get the active currency ID — uses session if set, otherwise fetches from WHMCS client profile.
     */
    private function getActiveCurrencyId(Request $request): int
    {
        // If already set in session, use it
        if ($request->session()->has('currency_id')) {
            return (int) $request->session()->get('currency_id');
        }

        // If user is authenticated, fetch their default currency from WHMCS
        if ($user = $request->user()) {
            try {
                $whmcs = app(WhmcsService::class);
                $details = $whmcs->getClientsDetails($user->whmcs_client_id);
                $currencyId = (int) ($details['currency'] ?? $details['client']['currency'] ?? 1);
                $request->session()->put('currency_id', $currencyId);
                return $currencyId;
            } catch (\Throwable) {
                // Fallback to 1
            }
        }

        return 1;
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
