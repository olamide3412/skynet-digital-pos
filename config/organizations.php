<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organizations Feature Flag
    |--------------------------------------------------------------------------
    |
    | When set to false, organization management screens, menus, and scoped
    | access rules remain inactive. Only Platform Super Admin features exist.
    |
    */

    'enabled' => (bool) env('ORGANIZATIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Reserved Branch Slugs
    |--------------------------------------------------------------------------
    |
    | Slugs reserved by the system to avoid collisions with root routes,
    | future organization portals, APIs, and authentication endpoints.
    |
    */

    'reserved_slugs' => [
        'admin',
        'api',
        'dashboard',
        'features',
        'industries',
        'login',
        'logout',
        'manifest.json',
        'org',
        'organization',
        'organizations',
        'platform',
        'pos',
        'pricing',
        'privacy',
        'privacy-policy',
        'refund-policy',
        'register',
        'security',
        'settings',
        'super-admin',
        'superadmin',
        'sw.js',
        'terms',
        'terms-and-conditions',
    ],

];
