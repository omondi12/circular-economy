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

    'nawiri_payroll' => [
        'base_url' => rtrim((string) env('NAWIRI_PAYROLL_BASE_URL', 'https://nawiri-server-production.up.railway.app'), '/'),
        'timeout' => (int) env('NAWIRI_PAYROLL_TIMEOUT', 35),
        'reconcile_after_seconds' => (int) env('NAWIRI_PAYROLL_RECONCILE_AFTER', 60),
        'reversal_window_hours' => (int) env('NAWIRI_PAYROLL_REVERSAL_WINDOW_HOURS', 72),
    ],

];
