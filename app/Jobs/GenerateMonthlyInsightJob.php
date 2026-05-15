<?php

namespace App\Jobs;

use App\Enums\AIInsightType;
use App\Services\AIInsightPersistenceService;
use App\Services\InsightDataService;
use App\Services\LLMInsightService;
use App\Services\RuleBasedInsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInsightJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 120;

    public function __construct(
        public int $userId,
        public string $periodKey
    ) {}

    public function handle(
        InsightDataService $insightDataService,
        RuleBasedInsightService $ruleBasedInsightService,
        LLMInsightService $llmInsightService,
        AIInsightPersistenceService $persistenceService
    ): void {
        $aggregated = $insightDataService->aggregateForPeriod($this->userId, $this->periodKey);
        $ruleInsights = $ruleBasedInsightService->generateInsights($this->userId, $this->periodKey);
        $narrative = $llmInsightService->generateNarrativeInsight($this->userId, $this->periodKey);

        $content = $narrative ?: $this->buildFallbackContent($ruleInsights, $aggregated);

        $persistenceService->saveInsight(
            userId: $this->userId,
            type: AIInsightType::MonthlySummary,
            periodKey: $this->periodKey,
            content: $content,
            metadata: [
                'rule_insights' => $ruleInsights,
                'aggregated_summary' => [
                    'total_income' => $aggregated['total_income'] ?? 0,
                    'total_expense' => $aggregated['total_expense'] ?? 0,
                    'net_cashflow' => $aggregated['net_cashflow'] ?? 0,
                ],
            ]
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateMonthlyInsightJob failed', [
            'user_id' => $this->userId,
            'period_key' => $this->periodKey,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $ruleInsights
     * @param  array<string, mixed>  $aggregated
     */
    private function buildFallbackContent(array $ruleInsights, array $aggregated): string
    {
        if (empty($ruleInsights)) {
            $income = (float) ($aggregated['total_income'] ?? 0);
            $expense = (float) ($aggregated['total_expense'] ?? 0);
            $net = (float) ($aggregated['net_cashflow'] ?? ($income - $expense));

            return "## Monthly Summary\n"
                ."Income: {$income}\nExpense: {$expense}\nNet cashflow: {$net}\n\n"
                ."## Key Points\nNo specific alerts for this period.";
        }

        $lines = ['## Monthly Summary'];
        foreach ($ruleInsights as $insight) {
            $title = (string) ($insight['title'] ?? 'Insight');
            $description = (string) ($insight['description'] ?? '');
            $lines[] = "- {$title}: {$description}";
        }

        $lines[] = '';
        $lines[] = '## Key Points';
        $lines[] = 'Generated from rule-based insights because narrative AI is unavailable.';

        return implode("\n", $lines);
    }
}
