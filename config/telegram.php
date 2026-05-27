<?php

return [

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

    'allowed_user_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TELEGRAM_ALLOWED_USER_IDS', ''))
    ))),

    'feature_enabled' => filter_var(env('TELEGRAM_FEATURE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'public_access' => filter_var(env('TELEGRAM_PUBLIC_ACCESS', false), FILTER_VALIDATE_BOOLEAN),

];
