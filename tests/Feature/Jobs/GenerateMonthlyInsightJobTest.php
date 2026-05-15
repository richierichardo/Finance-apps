<?php

use App\Enums\AIInsightType;
use App\Jobs\GenerateMonthlyInsightJob;
use App\Models\AIInsight;
use App\Models\User;
use App\Services\AIInsightPersistenceService;
use App\Services\InsightDataService;
use App\Services\LLMInsightService;
use App\Services\RuleBasedInsightService;
use Illuminate\Support\Str;

test('generate monthly insight job saves insight when llm returns empty', function () {
    $this->mock(LLMInsightService::class, function ($mock) {
        $mock->shouldReceive('generateNarrativeInsight')->once()->andReturn('');
    });

    $user = User::factory()->create(['username' => 'job_'.Str::random(8)]);

    $job = new GenerateMonthlyInsightJob($user->id, '2026-02');
    $job->handle(
        app(InsightDataService::class),
        app(RuleBasedInsightService::class),
        app(LLMInsightService::class),
        app(AIInsightPersistenceService::class),
    );

    $insight = AIInsight::query()
        ->forUser($user->id)
        ->forPeriod('2026-02')
        ->ofType(AIInsightType::MonthlySummary)
        ->first();

    expect($insight)->not->toBeNull()
        ->and($insight->content)->not->toBe('');
});
