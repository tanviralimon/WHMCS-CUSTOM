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

        // Get the client's active currency code
        $currencyCode = $this->getActiveCurrencyCode($request);

        // Filter hidden products and flatten pricing
        $products = [];
        foreach ($raw as $p) {
            // Skip hidden products
            if (!empty($p['hidden'])) continue;

            // Flatten pricing from nested currency structure
            // WHMCS returns: pricing.{CURRENCY}.{cycle} e.g. pricing.USD.monthly
            $pricing = $p['pricing'][$currencyCode] ?? $p['pricing'][array_key_first($p['pricing'] ?? [])] ?? [];
            $p['flatPricing'] = [
                'monthly'      => $this->cleanPrice($pricing['monthly'] ?? null),
                'quarterly'    => $this->cleanPrice($pricing['quarterly'] ?? null),
                'semiannually' => $this->cleanPrice($pricing['semiannually'] ?? null),
                'annually'     => $this->cleanPrice($pricing['annually'] ?? null),
                'biennially'   => $this->cleanPrice($pricing['biennially'] ?? null),
                'triennially'  => $this->cleanPrice($pricing['triennially'] ?? null),
            ];

            // Determine the "starting from" display price
            $displayPrice = null;
            foreach (['monthly', 'annually', 'quarterly', 'semiannually', 'biennially', 'triennially'] as $cycle) {
                if ($p['flatPricing'][$cycle] !== null && $p['flatPricing'][$cycle] >= 0) {
                    $displayPrice = $p['flatPricing'][$cycle];
                    $p['displayCycle'] = $cycle;
                    break;
                }
            }
            $p['displayPrice'] = $displayPrice;

            $products[] = $p;
        }

        return Inertia::render('Client/Orders/Products', [
            'groups'       => $groups,
            'products'     => $products,
            'activeGroup'  => $groupId,
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
        // Default to USD; override if client has a different currency
        $currencies = $this->whmcs->getCurrencies();
        $list = $currencies['currencies']['currency'] ?? [];
        if (!empty($list)) {
            // Try to match client's currency
            $clientCurrencyId = $request->user()->currency_id ?? 1;
            foreach ($list as $c) {
                if ((int) ($c['id'] ?? 0) === (int) $clientCurrencyId) {
                    return $c['code'] ?? 'USD';
                }
            }
            // Fallback to first/default
            foreach ($list as $c) {
                if (!empty($c['default'])) return $c['code'] ?? 'USD';
            }
            return $list[0]['code'] ?? 'USD';
        }
        return 'USD';
    }

    public function productDetail(Request $request, int $id)
    {
        $products = $this->whmcs->getProducts();
        $product  = null;
        foreach ($products['products']['product'] ?? [] as $p) {
            if ((int) $p['pid'] === $id) {
                // Don't allow viewing hidden products
                if (!empty($p['hidden'])) abort(404);
                $product = $p;
                break;
            }
        }

        if (!$product) {
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

        $paymentMethods = $this->whmcs->getPaymentMethods();

        return Inertia::render('Client/Orders/ProductDetail', [
            'product'        => $product,
            'paymentMethods' => $paymentMethods['paymentmethods']['paymentmethod'] ?? [],
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
            'price'         => 'required|string',
            'domain'        => 'nullable|string',
        ]);

        $cart = session('cart', ['items' => [], 'promo' => null]);
        $cart['items'][] = [
            'pid'          => $request->pid,
            'billingcycle' => $request->billingcycle,
            'name'         => $request->name,
            'price'        => $request->price,
            'domain'       => $request->domain,
            'configoptions' => $request->get('configoptions', []),
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

        if (!empty($pids)) {
            $orderData['pid']          = implode(',', $pids);
            $orderData['domain']       = implode(',', $domains);
            $orderData['billingcycle'] = implode(',', $cycles);
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

        $result = $this->whmcs->addOrder($clientId, $orderData);

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
}
