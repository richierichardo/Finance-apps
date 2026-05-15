<?php

use App\Services\BudgetService;

test('budget getStatus returns safe warning exceeded by percentage', function () {
    $service = app(BudgetService::class);

    expect($service->getStatus(50))->toBe('safe')
        ->and($service->getStatus(70))->toBe('warning')
        ->and($service->getStatus(100))->toBe('warning')
        ->and($service->getStatus(101))->toBe('exceeded');
});
