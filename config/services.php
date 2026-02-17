<?php

declare(strict_types=1);

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

    'google_mcp' => [
        'oauth_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'oauth_redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI'),

        'oauth_allowed_audiences' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_OAUTH_ALLOWED_AUDIENCES', ''))))),
        'calendar_default_id' => env('GOOGLE_CALENDAR_DEFAULT_ID', 'primary'),
        'external_test_calendar_id' => env('GOOGLE_EXTERNAL_TEST_CALENDAR_ID'),

        'mcp_required_scopes' => [
            'openid',
            'https://www.googleapis.com/auth/userinfo.email',
        ],

        'oauth_authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'oauth_token_endpoint' => 'https://oauth2.googleapis.com/token',
        'oauth_jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
        'oauth_token_info_endpoint' => 'https://oauth2.googleapis.com/tokeninfo',
        'calendar_api_base_url' => 'https://www.googleapis.com/calendar/v3',

        'token_file' => storage_path('app/mcp/google-calendar-tokens.json'),
        'idempotency_file' => storage_path('app/mcp/idempotency.json'),
    ],

];
