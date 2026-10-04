<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Commerce Integration Default Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for the independent ecommerce integration module.
    |
    */

    // Default time-to-live for a stock reservation hold in minutes
    'reservation_ttl_minutes' => env('COMMERCE_RESERVATION_TTL_MINUTES', 30),

    // Maximum allowable reservation TTL requested by an external ecommerce platform
    'max_reservation_ttl_minutes' => env('COMMERCE_MAX_RESERVATION_TTL_MINUTES', 120),

    // Catalog pagination limits
    'catalog' => [
        'default_per_page' => 50,
        'max_per_page' => 200,
    ],

    // Webhook settings
    'webhooks' => [
        'retry_attempts' => 3,
        'timeout_seconds' => 5,
    ],
];
