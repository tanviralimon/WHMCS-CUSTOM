<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gateway Module Mapping
    |--------------------------------------------------------------------------
    | Maps WHMCS gateway module names to our internal handlers.
    | Any gateway not listed here will fall back to SSO-based WHMCS redirect.
    |
    | API keys and credentials are pulled dynamically from WHMCS gateway
    | configuration (tblpaymentgateways) — no need to configure them here.
    */
    'supported_gateways' => [
        'stripe'           => 'stripe',
        'stripe_checkout'  => 'stripe',
        'stripecheckout'   => 'stripe',
        'sslcommerz'       => 'sslcommerz',
        'sslcommerzs'      => 'sslcommerz',
        'banktransfer'     => 'banktransfer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Secret Key Override
    |--------------------------------------------------------------------------
    | If WHMCS uses a restricted key (rk_live_*), it can't create Checkout
    | Sessions. Set a full secret key (sk_live_* / sk_test_*) here to enable
    | native Stripe Checkout. If empty, Stripe payments fall back to SSO
    | (paying through WHMCS directly).
    */
    'stripe_secret_key' => env('STRIPE_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Ticket Upload Limits (Payment Proof)
    |--------------------------------------------------------------------------
    | Must match your WHMCS ticket attachment settings:
    | Setup → General Settings → Support → Ticket File Upload Settings
    */
    'ticket_max_file_size_mb' => env('TICKET_MAX_FILE_SIZE_MB', 2),
    'ticket_allowed_extensions' => env('TICKET_ALLOWED_EXTENSIONS', 'jpg,gif,jpeg,png,txt,pdf'),
];
