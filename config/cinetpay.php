<?php

return [
    'api_key' => env('CINETPAY_API_KEY'),
    'api_password' => env('CINETPAY_API_PASSWORD'),
    'environment' => env('CINETPAY_ENVIRONMENT', env('CINETPAY_SANDBOX', true) ? 'sandbox' : 'production'),
    'default_country' => env('CINETPAY_DEFAULT_COUNTRY', 'BF'),
    'default_currency' => env('CINETPAY_DEFAULT_CURRENCY', 'XOF'),
    'timeout' => (float) env('CINETPAY_TIMEOUT', 20.0),
    'connect_timeout' => (float) env('CINETPAY_CONNECT_TIMEOUT', 5.0),
];
