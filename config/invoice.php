<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Details for PDF Invoices
    |--------------------------------------------------------------------------
    |
    | These details appear in the header of generated PDF invoices.
    | Update them to match your business registration info.
    |
    */

    'company_address1'  => env('INVOICE_COMPANY_ADDRESS1', ''),
    'company_address2'  => env('INVOICE_COMPANY_ADDRESS2', ''),
    'company_city'      => env('INVOICE_COMPANY_CITY', ''),
    'company_state'     => env('INVOICE_COMPANY_STATE', ''),
    'company_postcode'  => env('INVOICE_COMPANY_POSTCODE', ''),
    'company_country'   => env('INVOICE_COMPANY_COUNTRY', ''),
    'company_phone'     => env('INVOICE_COMPANY_PHONE', ''),
    'company_email'     => env('INVOICE_COMPANY_EMAIL', 'support@orcustech.com'),
    'company_tax_id'    => env('INVOICE_COMPANY_TAX_ID', ''),
];
