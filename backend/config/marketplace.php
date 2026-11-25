<?php

return [
    'platform_commission_enabled' => env(
        'PLATFORM_COMMISSION_ENABLED',
        false
    ),

    'order_expiry_enabled' => env(
        'ORDER_EXPIRY_ENABLED',
        false
    ),

    'order_expiry_minutes' => env(
        'ORDER_EXPIRY_MINUTES',
        60
    ),
];
