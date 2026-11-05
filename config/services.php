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

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:7b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 180),
    ],

    'amocrm' => [
        'base_url' => env('AMOCRM_BASE_URL'),
        'access_token' => env('AMOCRM_ACCESS_TOKEN'),
        'webhook_secret' => env('AMOCRM_WEBHOOK_SECRET'),
        'history_limit' => (int) env('AMOCRM_HISTORY_LIMIT', 20),
        'suggestion_delay' => (int) env('AMOCRM_SUGGESTION_DELAY', 5),
    ],

];
