<?php

use App\Services\InsightDataService;
use App\Services\RuleBasedInsightService;

test('rule based service emits budget exceeded insight from aggregate', function () {
    $this->mock(InsightDataService::class, function ($mock) {
        $mock->shouldReceive('aggregateForPeriod')
            ->once()
            ->with(1, '2026-05')
            ->andReturn([
                'total_income' => 0,
                'total_expense' => 0,
                'budget_usage' => [[
                    'category' => 'Food',
                    'budget_amount' => 100,
                    'spent' => 150,
                    'remaining' => 0,
                    'percentage' => 150.0,
                    'status' => 'exceeded',
                ]],
            ]);

        $mock->shouldReceive('getPreviousPeriodKey')
            ->andThrow(new RuntimeException('no previous'));
    });

    $service = app(RuleBasedInsightService::class);
    $insights = $service->generateInsights(1, '2026-05');

    $types = array_column($insights, 'type');
    expect($types)->toContain('budget_exceeded');
});
