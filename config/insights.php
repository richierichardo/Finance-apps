<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Insight Thresholds
    |--------------------------------------------------------------------------
    */

    'large_transaction_threshold_percent' => env('INSIGHT_LARGE_TRANSACTION_THRESHOLD', 20),
    'spending_increase_threshold_percent' => env('INSIGHT_SPENDING_INCREASE_THRESHOLD', 20),
    'budget_warning_threshold' => env('INSIGHT_BUDGET_WARNING_THRESHOLD', 70),

    'llm_enabled' => env('INSIGHT_LLM_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Period Format
    |--------------------------------------------------------------------------
    | Format for period_key (e.g. 2025-02 for February 2025).
    */

    'period_key_format' => 'Y-m',
];
