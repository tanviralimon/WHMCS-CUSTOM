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
        $products = $this->whmcs->getProducts($groupId ? (int) $groupId : null);

        return Inertia::render('Client/Orders/Products', [
            'groups'       => $groups,
            'products'     => $products['products']['product'] ?? [],
            'activeGroup'  => $groupId,
        ]);
    }

    public function productDetail(int $id)
    {
        $products = $this->whmcs->getProducts();
        $product  = null;
        foreach ($products['products']['product'] ?? [] as $p) {
            if ((int) $p['pid'] === $id) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            abort(404);
        }

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

        // Build product arrays
        $pids    = [];
        $domains = [];
        $cycles  = [];
        foreach ($cart['items'] as $item) {
            $pids[]    = $item['pid'];
            $domains[] = $item['domain'] ?? '';
            $cycles[]  = $item['billingcycle'];
        }
        $orderData['pid']          = implode(',', $pids);
        $orderData['domain']       = implode(',', $domains);
        $orderData['billingcycle'] = implode(',', $cycles);

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
