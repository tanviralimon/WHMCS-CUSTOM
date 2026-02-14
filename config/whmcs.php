<?php

return [
    'base_url'       => env('WHMCS_BASE_URL', 'https://your-whmcs-domain.com'),
    'api_identifier' => env('WHMCS_API_IDENTIFIER', ''),
    'api_secret'     => env('WHMCS_API_SECRET', ''),
    'api_timeout'    => env('WHMCS_API_TIMEOUT', 10),
    'verify_ssl'     => env('WHMCS_VERIFY_SSL', true),
];
