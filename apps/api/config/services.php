<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'paystack' => [
        'enabled' => env('FEATURE_PAYSTACK', false),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'callback_url' => env('PAYSTACK_CALLBACK_URL', 'http://localhost:3000/payment/callback'),
    ],

    'vtpass' => [
        'enabled' => env('FEATURE_VTPASS', false),
        'auto_fulfill' => env('FEATURE_VTPASS_AUTO_FULFILL', false),
        'base_url' => env('VTPASS_BASE_URL', 'https://sandbox.vtpass.com'),
        'username' => env('VTPASS_USERNAME'),
        'password' => env('VTPASS_PASSWORD'),
        'api_key' => env('VTPASS_API_KEY'),
        'public_key' => env('VTPASS_PUBLIC_KEY'),
        'secret_key' => env('VTPASS_SECRET_KEY'),
        'timeout' => env('VTPASS_TIMEOUT', 30),
        'retry_times' => env('VTPASS_RETRY_TIMES', 2),
        'retry_sleep_ms' => env('VTPASS_RETRY_SLEEP_MS', 500),
        'test_disco' => env('VTPASS_TEST_DISCO', 'IKEDC'),
        'test_meter_number' => env('VTPASS_TEST_METER_NUMBER', '45053854956'),
        'test_meter_type' => env('VTPASS_TEST_METER_TYPE', 'prepaid'),
    ],

    'operator' => [
        'access_key' => env('OPERATOR_ACCESS_KEY'),
    ],

];
