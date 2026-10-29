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

    'public_dashboard' => [
        'key' => env('PUBLIC_DASHBOARD_KEY'),
    ],

    // Agent 360 (RM register shared with Adam AHB Souq): key it sends to /api/agent360/v1. Empty = the API is off.
    'agent360' => [
        'key' => env('AGENT360_API_KEY'),
        // RM single sign-in: Agent 360's public key (base64). Set = RMs can open Westport from Agent 360.
        'sso_public_key' => env('AGENT360_SSO_PUBLIC_KEY'),
        // true = RMs may ONLY sign in through Agent 360 (switch on once every RM has an Agent 360 password)
        'sso_only' => (bool) env('AGENT360_SSO_ONLY', false),
        'sso_login_url' => env('AGENT360_LOGIN_URL', 'https://agent360.co.ke/rm/login'),
        // true = RMs are paid from Agent 360 (requisitions and commissions); Westport's own Pay is switched off
        'payments' => (bool) env('AGENT360_PAYMENTS', false),
    ],

    'nawiri_payroll' => [
        'base_url' => rtrim((string) env('NAWIRI_PAYROLL_BASE_URL', 'https://nawiri-server-production.up.railway.app'), '/'),
        'timeout' => (int) env('NAWIRI_PAYROLL_TIMEOUT', 35),
        'reconcile_after_seconds' => (int) env('NAWIRI_PAYROLL_RECONCILE_AFTER', 60),
        'reversal_window_hours' => (int) env('NAWIRI_PAYROLL_REVERSAL_WINDOW_HOURS', 72),
    ],

];
