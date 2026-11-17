<?php

return [
    'token' => env('PAWAPAY_API_TOKEN', env('PAWAPAY_TOKEN')),

    'sandbox' => env('PAWAPAY_SANDBOX', true),

    'timeout' => env('PAWAPAY_TIMEOUT', 30),

    'default_country' => env('PAWAPAY_DEFAULT_COUNTRY', 'BFA'),

    'default_currency' => env('PAWAPAY_DEFAULT_CURRENCY', 'XOF'),

    'webhook' => [
        'path' => env('PAWAPAY_WEBHOOK_PATH', '/api/webhooks/pawapay'),
        'secret' => env('PAWAPAY_WEBHOOK_SECRET'),
    ],
];
