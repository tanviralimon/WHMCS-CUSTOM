<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'enabled'        => (bool) env('STRIPE_ENABLED', false),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'secret_key'     => env('STRIPE_SECRET_KEY', ''),
        'currency'       => env('STRIPE_CURRENCY', 'usd'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Transfer Configuration
    |--------------------------------------------------------------------------
    */
    'bank_transfer' => [
        'enabled'      => (bool) env('BANK_TRANSFER_ENABLED', false),
        'bank_name'    => env('BANK_NAME', ''),
        'account_name' => env('BANK_ACCOUNT_NAME', ''),
        'account_number' => env('BANK_ACCOUNT_NUMBER', ''),
        'routing_number' => env('BANK_ROUTING_NUMBER', ''),
        'swift_code'   => env('BANK_SWIFT_CODE', ''),
        'iban'         => env('BANK_IBAN', ''),
        'instructions' => env('BANK_INSTRUCTIONS', 'Please include your invoice number as the payment reference.'),
    ],
];
