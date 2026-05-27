<?php

return [

    'provider' => env('AI_PROVIDER', 'qwen'),

    'model' => env('AI_MODEL', 'qwen-plus'),

    'base_url' => rtrim(env('AI_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/'),

    'api_key' => env('AI_API_KEY'),

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 30),

    'intent_timeout_seconds' => (int) env('AI_INTENT_TIMEOUT_SECONDS', 8),

    'response_timeout_seconds' => (int) env('AI_RESPONSE_TIMEOUT_SECONDS', 15),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 1200),

    'out_of_scope_max_tokens' => (int) env('AI_OUT_OF_SCOPE_MAX_TOKENS', 300),

    'telegram_max_tokens' => (int) env('AI_TELEGRAM_MAX_TOKENS', 500),

    'dashboard_max_tokens' => (int) env('AI_DASHBOARD_MAX_TOKENS', 800),

    'intent_max_tokens' => (int) env('AI_INTENT_MAX_TOKENS', 400),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    'draft_expiry_minutes' => (int) env('AI_DRAFT_EXPIRY_MINUTES', 30),

    'link_token_expiry_minutes' => (int) env('TELEGRAM_LINK_TOKEN_EXPIRY_MINUTES', 10),

    'access' => [
        'feature_enabled' => filter_var(env('AI_FEATURE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'public_access' => filter_var(env('AI_PUBLIC_ACCESS', false), FILTER_VALIDATE_BOOLEAN),
        'allowed_user_ids' => array_values(array_filter(array_map(
            'intval',
            array_map('trim', explode(',', (string) env('AI_ALLOWED_USER_IDS', '')))
        ))),
        'daily_request_limit' => (int) env('AI_DAILY_REQUEST_LIMIT', 20),
        'telegram_actions_per_minute' => (int) env('AI_TELEGRAM_ACTIONS_PER_MINUTE', 5),
        'denial_message' => 'Fitur AI Flowlet masih dalam beta terbatas. Kamu tetap bisa memakai fitur pencatatan wallet, transaksi, budget, dan recurring secara manual.',
    ],

];
