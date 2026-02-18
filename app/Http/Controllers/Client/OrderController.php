<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function products(Request $request)
    {
        $groups   = $this->whmcs->getProductGroups();
        $groupId  = $request->get('group');
        $result   = $this->whmcs->getProducts($groupId ? (int) $groupId : null);
        $raw      = $result['products']['product'] ?? [];

        // Get the group name map (gid => name) and hidden group IDs from WHMCS database
        $groupNames   = $this->whmcs->getProductGroupNames();
        $hiddenGroups = $this->whmcs->getHiddenGroupIds();

        // Get the client's active currency code and its prefix/suffix
        $currencyCode = $this->getActiveCurrencyCode($request);
        $currencyPrefix = '';
        $currencySuffix = '';

        // Filter hidden products/groups, flatten pricing, group by category
        // MarketConnect products are exempt from hidden-group filter because WHMCS
        // auto-hides their groups (they use special storefront pages instead).
        $grouped = [];    // gid => ['group' => [...], 'products' => [...]]
        $groupOrder = [];  // preserve WHMCS ordering by first-seen gid

        foreach ($raw as $p) {
            if (!empty($p['hidden'])) continue;
            $gid = $p['gid'] ?? 0;
            // Skip products from hidden groups, unless they are MarketConnect products
            if (in_array($gid, $hiddenGroups) && ($p['module'] ?? '') !== 'marketconnect') continue;

            $pricing = $p['pricing'][$currencyCode] ?? $p['pricing'][array_key_first($p['pricing'] ?? [])] ?? [];

            // Grab currency prefix/suffix from the pricing data
            if (!$currencyPrefix && !empty($pricing['prefix'])) {
                $currencyPrefix = $pricing['prefix'];
                $currencySuffix = $pricing['suffix'] ?? '';
            }

            $p['flatPricing'] = [
                'monthly'      => $this->cleanPrice($pricing['monthly'] ?? null),
                'quarterly'    => $this->cleanPrice($pricing['quarterly'] ?? null),
                'semiannually' => $this->cleanPrice($pricing['semiannually'] ?? null),
                'annually'     => $this->cleanPrice($pricing['annually'] ?? null),
                'biennially'   => $this->cleanPrice($pricing['biennially'] ?? null),
                'triennially'  => $this->cleanPrice($pricing['triennially'] ?? null),
            ];

            $displayPrice = null;
            $displayCycle = null;
            foreach (['monthly', 'annually', 'quarterly', 'semiannually', 'biennially', 'triennially'] as $cycle) {
                if ($p['flatPricing'][$cycle] !== null && $p['flatPricing'][$cycle] >= 0) {
                    $displayPrice = $p['flatPricing'][$cycle];
                    $displayCycle = $cycle;
                    break;
                }
            }
            $p['displayPrice'] = $displayPrice;
            $p['displayCycle'] = $displayCycle;

            if (!isset($grouped[$gid])) {
                $grouped[$gid] = [
                    'group' => [
                        'id'   => $gid,
                        'name' => $groupNames[$gid] ?? $p['groupname'] ?? 'Products',
                    ],
                    'products' => [],
                ];
                $groupOrder[] = $gid;
            }
            $grouped[$gid]['products'][] = $p;
        }

        // Build category-sorted list preserving WHMCS group order
        $categories = [];
        foreach ($groupOrder as $gid) {
            $categories[] = $grouped[$gid];
        }

        // Also build a flat products list (for filtered view)
        $flatProducts = [];
        foreach ($categories as $cat) {
            foreach ($cat['products'] as $p) {
                $flatProducts[] = $p;
            }
        }

        return Inertia::render('Client/Orders/Products', [
            'groups'         => $groups,
            'categories'     => $categories,
            'products'       => $flatProducts,
            'activeGroup'    => $groupId,
            'currencyPrefix' => $currencyPrefix,
            'currencySuffix' => $currencySuffix,
        ]);
    }

    /**
     * Clean a WHMCS price value: returns float or null if not orderable (-1).
     */
    private function cleanPrice($val): ?float
    {
        if ($val === null || $val === '' || $val === '-1' || $val === '-1.00') {
            return null;
        }
        $num = (float) preg_replace('/[^0-9.\-]/', '', $val);
        return $num >= 0 ? $num : null;
    }

    /**
     * Get the active currency code for the current user.
     */
    private function getActiveCurrencyCode(Request $request): string
    {
        // Use the session-based currency_id (set by HandleInertiaRequests middleware
        // from WHMCS GetClientsDetails or CurrencyController::switch)
        $clientCurrencyId = (int) session('currency_id', 0);

        // If no session yet, try to resolve from WHMCS client profile
        if (!$clientCurrencyId && $user = $request->user()) {
            try {
                $details = $this->whmcs->getClientsDetails($user->whmcs_client_id);
                $clientCurrencyId = (int) ($details['currency'] ?? $details['client']['currency'] ?? 1);
                session(['currency_id' => $clientCurrencyId]);
            } catch (\Throwable) {
                $clientCurrencyId = 1;
            }
        }

        if (!$clientCurrencyId) $clientCurrencyId = 1;

        $currencies = $this->whmcs->getCurrencies();
        $list = $currencies['currencies']['currency'] ?? [];
        foreach ($list as $c) {
            if ((int) ($c['id'] ?? 0) === $clientCurrencyId) {
                return $c['code'] ?? 'USD';
            }
        }
        // Fallback to default currency
        foreach ($list as $c) {
            if (!empty($c['default'])) return $c['code'] ?? 'USD';
        }
        return $list[0]['code'] ?? 'USD';
    }

    public function productDetail(Request $request, int $id)
    {
        // Fetch specific product by pid to ensure configoptions are fully included
        $singleResult = $this->whmcs->getProductById($id);
        $product = $singleResult['products']['product'][0] ?? null;

        if (!$product || !empty($product['hidden'])) {
            abort(404);
        }

        // Flatten pricing
        $currencyCode = $this->getActiveCurrencyCode($request);
        $pricing = $product['pricing'][$currencyCode] ?? $product['pricing'][array_key_first($product['pricing'] ?? [])] ?? [];
        $product['flatPricing'] = [
            'monthly'      => $this->cleanPrice($pricing['monthly'] ?? null),
            'quarterly'    => $this->cleanPrice($pricing['quarterly'] ?? null),
            'semiannually' => $this->cleanPrice($pricing['semiannually'] ?? null),
            'annually'     => $this->cleanPrice($pricing['annually'] ?? null),
            'biennially'   => $this->cleanPrice($pricing['biennially'] ?? null),
            'triennially'  => $this->cleanPrice($pricing['triennially'] ?? null),
        ];

        // Determine if this product type requires a domain
        $requiresDomain = in_array($product['type'] ?? '', ['hostingaccount', 'reselleraccount', 'server']);

        // Get configurable options for this product and normalize field names/pricing
        $rawConfigOptions = $product['configoptions']['configoption'] ?? [];
        // WHMCS may return a single configoption as an object instead of array
        if (!empty($rawConfigOptions) && !isset($rawConfigOptions[0])) {
            $rawConfigOptions = [$rawConfigOptions];
        }
        $configOptions = $this->normalizeConfigOptions($rawConfigOptions, $currencyCode);

        // Get custom fields (shown on order form) — includes VPS fields like Hostname, Root Password, NS prefixes
        $rawCustomFields = $product['customfields']['customfield'] ?? [];
        if (!empty($rawCustomFields) && !isset($rawCustomFields[0])) {
            $rawCustomFields = [$rawCustomFields];
        }
        $customFields = array_map(fn($cf) => [
            'id'          => (int) ($cf['id'] ?? 0),
            'name'        => $cf['name'] ?? '',
            'description' => $cf['description'] ?? '',
            'required'    => !empty($cf['required']),
        ], $rawCustomFields);

        // Detect if this product requires server configuration (VPS / Dedicated Server)
        $requiresServerConfig = in_array($product['type'] ?? '', ['server']) ||
            in_array(strtolower($product['module'] ?? ''), ['virtualizor', 'solusvm', 'proxmox', 'virtuozzo', 'solusio']);

        $paymentMethods = $this->whmcs->getPaymentMethods();

        // Get TLD pricing for domain registration if required
        $tldPricing = [];
        if ($requiresDomain) {
            $clientCurrencyId = (int) session('currency_id', 1);
            $tldResult = $this->whmcs->getTLDPricing($clientCurrencyId);
            foreach ($tldResult['pricing'] ?? [] as $tld => $tp) {
                $registerPrice = null;
                if (isset($tp['register']) && is_array($tp['register'])) {
                    foreach ($tp['register'] as $years => $price) {
                        $registerPrice = $price;
                        break;
                    }
                }
                $tldPricing[] = [
                    'tld'      => $tld,
                    'register' => $registerPrice,
                ];
            }
        }

        return Inertia::render('Client/Orders/ProductDetail', [
            'product'              => $product,
            'paymentMethods'       => $paymentMethods['paymentmethods']['paymentmethod'] ?? [],
            'requiresDomain'       => $requiresDomain,
            'requiresServerConfig' => $requiresServerConfig,
            'configOptions'        => $configOptions,
            'customFields'         => $customFields,
            'tldPricing'           => $tldPricing,
            'currencyPrefix'       => $pricing['prefix'] ?? '',
            'currencySuffix'       => $pricing['suffix'] ?? '',
        ]);
    }

    /**
     * AJAX: Check domain availability from the product detail page.
     */
    public function checkDomainAvailability(Request $request)
    {
        $request->validate(['domain' => 'required|string|max:255']);
        $domain = trim($request->domain);

        if (!str_contains($domain, '.')) {
            $popularTlds = ['.com', '.net', '.org', '.io', '.co.uk', '.co', '.info', '.xyz', '.online', '.tech'];
            $results = [];
            foreach ($popularTlds as $tld) {
                $check = $this->whmcs->domainWhois($domain . $tld);
                $results[] = [
                    'domain' => $domain . $tld,
                    'status' => $check['status'] ?? 'unknown',
                ];
            }
            return response()->json(['results' => $results]);
        }

        $check = $this->whmcs->domainWhois($domain);
        return response()->json([
            'results' => [[
                'domain' => $domain,
                'status' => $check['status'] ?? 'unknown',
            ]],
        ]);
    }

    public function cart(Request $request)
    {
        $cart = session('cart', ['items' => [], 'promo' => null]);
        $paymentMethods = $this->whmcs->getPaymentMethods();

        return Inertia::render('Client/Orders/Cart', [
            'cart'           => $cart,
            'paymentMethods' => $paymentMethods['paymentmethods']['paymentmethod'] ?? [],
        ]);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'pid'           => 'required|integer',
            'billingcycle'  => 'required|string',
            'name'          => 'required|string',
            'price'         => 'required',
            'domain'        => 'nullable|string',
            'hostname'      => 'nullable|string|max:255',
            'rootpw'        => 'nullable|string|max:255',
            'ns1prefix'     => 'nullable|string|max:100',
            'ns2prefix'     => 'nullable|string|max:100',
        ]);

        $cart = session('cart', ['items' => [], 'promo' => null]);
        $cart['items'][] = [
            'pid'           => $request->pid,
            'billingcycle'  => $request->billingcycle,
            'name'          => $request->name,
            'price'         => (string) $request->price,
            'domain'        => $request->domain,
            'configoptions' => $request->get('configoptions', []),
            'customfields'  => $request->get('customfields', []),
            'hostname'      => $request->hostname,
            'rootpw'        => $request->rootpw,
            'ns1prefix'     => $request->ns1prefix,
            'ns2prefix'     => $request->ns2prefix,
        ];
        session(['cart' => $cart]);

        return redirect()->route('client.orders.cart')->with('success', 'Item added to cart.');
    }

    public function removeFromCart(Request $request, int $index)
    {
        $cart = session('cart', ['items' => [], 'promo' => null]);
        if (isset($cart['items'][$index])) {
            array_splice($cart['items'], $index, 1);
        }
        session(['cart' => $cart]);

        return back()->with('success', 'Item removed from cart.');
    }

    public function applyPromo(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $result = $this->whmcs->getPromotions($request->code);
        $promos = $result['promotions']['promotion'] ?? [];

        if (empty($promos)) {
            return back()->withErrors(['code' => 'Invalid promo code.']);
        }

        $cart = session('cart', ['items' => [], 'promo' => null]);
        $cart['promo'] = $promos[0];
        session(['cart' => $cart]);

        return back()->with('success', 'Promo code applied.');
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'paymentmethod' => 'required|string',
        ]);

        $clientId = $request->user()->whmcs_client_id;
        $cart     = session('cart', ['items' => [], 'promo' => null]);

        if (empty($cart['items'])) {
            return back()->withErrors(['cart' => 'Your cart is empty.']);
        }

        $orderData = [
            'paymentmethod' => $request->paymentmethod,
        ];

        // Build product arrays and domain arrays
        $pids    = [];
        $domains = [];
        $cycles  = [];
        $domainItems = [];

        foreach ($cart['items'] as $item) {
            if (($item['type'] ?? '') === 'domain') {
                $domainItems[] = $item;
            } else {
                $pids[]    = $item['pid'];
                $domains[] = $item['domain'] ?? '';
                $cycles[]  = $item['billingcycle'];
            }
        }

        // Collect server config and custom/config option data per product
        $hostnames  = [];
        $rootpws    = [];
        $ns1s       = [];
        $ns2s       = [];
        $customfieldsArr  = [];
        $configoptionsArr = [];

        foreach ($cart['items'] as $item) {
            if (($item['type'] ?? '') === 'domain') continue;

            $hostnames[]  = $item['hostname'] ?? '';
            $rootpws[]    = $item['rootpw'] ?? '';
            $ns1s[]       = $item['ns1prefix'] ?? '';
            $ns2s[]       = $item['ns2prefix'] ?? '';

            // Custom fields: base64_encode(serialize([fieldId => value, ...]))
            $cf = $item['customfields'] ?? [];
            $customfieldsArr[] = !empty($cf) ? base64_encode(serialize($cf)) : base64_encode(serialize([]));

            // Config options: base64_encode(serialize([optionId => value, ...]))
            $co = $item['configoptions'] ?? [];
            $configoptionsArr[] = !empty($co) ? base64_encode(serialize($co)) : base64_encode(serialize([]));
        }

        if (!empty($pids)) {
            $orderData['pid']          = $pids;
            $orderData['domain']       = $domains;
            $orderData['billingcycle'] = $cycles;
            $orderData['hostname']     = $hostnames;
            $orderData['rootpw']       = $rootpws;
            $orderData['ns1prefix']    = $ns1s;
            $orderData['ns2prefix']    = $ns2s;
            $orderData['customfields'] = $customfieldsArr;
            $orderData['configoptions'] = $configoptionsArr;
        }

        // Add domain registrations
        if (!empty($domainItems)) {
            $regDomains = [];
            $regPeriods = [];
            $transferDomains = [];
            $transferPeriods = [];

            foreach ($domainItems as $d) {
                if (($d['domaintype'] ?? 'register') === 'transfer') {
                    $transferDomains[] = $d['domain'];
                    $transferPeriods[] = $d['regperiod'] ?? 1;
                } else {
                    $regDomains[] = $d['domain'];
                    $regPeriods[] = $d['regperiod'] ?? 1;
                }
            }

            if (!empty($regDomains)) {
                $orderData['domainregister'] = $regDomains;
                $orderData['domainregperiod'] = $regPeriods;
            }
            if (!empty($transferDomains)) {
                $orderData['domaintransfer'] = $transferDomains;
                $orderData['domaintransferperiod'] = $transferPeriods;
            }
        }

        if (!empty($cart['promo'])) {
            $orderData['promocode'] = $cart['promo']['code'] ?? '';
        }

        try {
            $result = $this->whmcs->addOrder($clientId, $orderData);
        } catch (\App\Exceptions\WhmcsApiException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Checkout failed', ['error' => $e->getMessage(), 'orderData' => $orderData]);
            return back()->withErrors(['checkout' => 'Something went wrong placing your order. Please try again.']);
        }

        // Clear cart
        session()->forget('cart');

        $invoiceId = $result['invoiceid'] ?? null;
        if ($invoiceId) {
            return redirect()
                ->route('client.invoices.show', $invoiceId)
                ->with('success', 'Order placed successfully! Invoice #' . $invoiceId . ' created.');
        }

        return redirect()->route('client.dashboard')
            ->with('success', 'Order placed successfully!');
    }

    public function orders(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $page     = max(1, (int) $request->get('page', 1));
        $perPage  = 25;

        $result = $this->whmcs->getOrders($clientId, ($page - 1) * $perPage, $perPage);

        return Inertia::render('Client/Orders/Index', [
            'orders'  => $result['orders']['order'] ?? [],
            'total'   => (int) ($result['totalresults'] ?? 0),
            'page'    => $page,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Normalize WHMCS configurable options into a consistent structure for the frontend.
     *
     * WHMCS API returns: name, type, options.option[].name, options.option[].pricing.{CURRENCY}.monthly
     * We normalize to: optionname, optiontype, options.option[].optionname, options.option[].pricing (flat string)
     */
    private function normalizeConfigOptions(array $rawOptions, string $currencyCode): array
    {
        $normalized = [];
        foreach ($rawOptions as $opt) {
            $item = [
                'id'         => $opt['id'] ?? 0,
                'optionname' => $opt['name'] ?? $opt['optionname'] ?? '',
                'optiontype' => (string) ($opt['type'] ?? $opt['optiontype'] ?? '1'),
                'minqty'     => $opt['minqty'] ?? 0,
                'maxqty'     => $opt['maxqty'] ?? 0,
                'options'    => ['option' => []],
            ];

            $subOptions = $opt['options']['option'] ?? [];
            // WHMCS may return a single sub-option as an object instead of array
            if (!empty($subOptions) && !isset($subOptions[0])) {
                $subOptions = [$subOptions];
            }

            foreach ($subOptions as $sub) {
                $flatPrice = null;
                $pricingData = $sub['pricing'] ?? [];
                // Extract the monthly price for the active currency
                $currencyPricing = $pricingData[$currencyCode]
                    ?? $pricingData[array_key_first($pricingData) ?: 'USD']
                    ?? [];
                if (is_array($currencyPricing)) {
                    $flatPrice = $currencyPricing['monthly'] ?? $currencyPricing['annually'] ?? null;
                    if ($flatPrice !== null) {
                        $flatPrice = $this->cleanPrice($flatPrice);
                    }
                } elseif (is_string($currencyPricing) || is_numeric($currencyPricing)) {
                    $flatPrice = $this->cleanPrice($currencyPricing);
                }

                $item['options']['option'][] = [
                    'id'         => $sub['id'] ?? 0,
                    'optionname' => $sub['name'] ?? $sub['optionname'] ?? '',
                    'rawName'    => $sub['rawName'] ?? null,
                    'recurring'  => $sub['recurring'] ?? 0,
                    'required'   => $sub['required'] ?? null,
                    'pricing'    => $flatPrice,
                ];
            }

            $normalized[] = $item;
        }
        return $normalized;
    }
}
