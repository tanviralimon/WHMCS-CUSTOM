<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    | Handles gateways: stripe, stripe_checkout
    */
    'stripe' => [
        'secret_key'     => env('STRIPE_SECRET_KEY', ''),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'currency'       => env('STRIPE_CURRENCY', 'usd'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSLCommerz Configuration
    |--------------------------------------------------------------------------
    | Handles gateway: sslcommerz
    */
    'sslcommerz' => [
        'store_id'       => env('SSLCOMMERZ_STORE_ID', ''),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', ''),
        'sandbox'        => (bool) env('SSLCOMMERZ_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Module Mapping
    |--------------------------------------------------------------------------
    | Maps WHMCS gateway module names to our internal handlers.
    | Any gateway not listed here will fall back to SSO-based WHMCS redirect.
    */
    'supported_gateways' => [
        'stripe'           => 'stripe',
        'stripe_checkout'  => 'stripe',
        'stripecheckout'   => 'stripe',
        'sslcommerz'       => 'sslcommerz',
    ],
];
