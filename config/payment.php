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
    ],
];
