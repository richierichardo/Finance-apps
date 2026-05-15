<?php

use App\Services\ForecastInsightService;
use App\Services\InsightDataService;

test('forecast insight averages three months of aggregates', function () {
    $this->mock(InsightDataService::class, function ($mock) {
        $mock->shouldReceive('aggregateForPeriod')
            ->times(3)
            ->andReturn([
                'total_income' => 300,
                'total_expense' => 150,
            ]);
    });

    $service = app(ForecastInsightService::class);
    $result = $service->generateForecastInsight(1, now()->format('Y-m'));

    expect($result['forecast_available'])->toBeTrue()
        ->and($result['projected_income'])->toBe(300.0)
        ->and($result['projected_expense'])->toBe(150.0)
        ->and($result['projected_net'])->toBe(150.0);
});
