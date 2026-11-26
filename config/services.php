<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'flowseller' => [
        'url' => env('SUPPLIER_API_URL', 'https://flowseller-api.pe-supplykh.com'),
        'key' => env('SUPPLIER_API_KEY'),
    ],

    'cinetpay' => [
        'api_key' => env('CINETPAY_API_KEY'),
        'api_password' => env('CINETPAY_API_PASSWORD'),
        'environment' => env('CINETPAY_ENVIRONMENT', 'sandbox'),
    ],

    'nowpayments' => [
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        'base_url' => env('NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io/v1'),
        // Les frais réseau et de conversion sont payés par le client (taux figé 10 minutes). false = à la charge du marchand.
        'fees_paid_by_user' => (bool) env('NOWPAYMENTS_FEES_PAID_BY_USER', true),
    ],

];
