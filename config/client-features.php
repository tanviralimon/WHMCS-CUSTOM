<?php

/**
 * Feature flags for optional client portal modules.
 * Set to false to hide menu items and disable routes.
 */
return [
    'domains'      => true,
    'knowledgebase' => true,
    'announcements' => true,
    'downloads'    => true,
    'affiliates'   => false, // Enable if your WHMCS has affiliates
    'sso'          => true,  // SSO auto-login from WHMCS
    'quotes'       => true,
    'addons'       => true,
    'orders'       => true,
];
