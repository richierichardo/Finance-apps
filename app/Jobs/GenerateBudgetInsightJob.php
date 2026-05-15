<?php

namespace App\Jobs;

use App\Enums\AIInsightType;
use App\Models\Budget;
use App\Services\AIInsightPersistenceService;
use App\Services\BudgetService;
use App\Services\InsightDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateBudgetInsightJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public int $timeout = 120;

    public function __construct(
        public int $userId,
        public int $budgetId,
        public ?string $periodKey = null
    ) {}

    public function handle(
        BudgetService $budgetService,
        InsightDataService $insightDataService,
        AIInsightPersistenceService $persistenceService
    ): void {
        $periodKey = $this->periodKey ?: now()->format(config('insights.period_key_format', 'Y-m'));

        $budget = Budget::query()
            ->with('category')
            ->find($this->budgetId);

        if (! $budget || (int) $budget->user_id !== $this->userId) {
            return;
        }

        [$start, $end] = $insightDataService->getPeriodRange($periodKey);
        $progress = $budgetService->getProgressForBudgetInPeriod($budget, $this->userId, $start, $end);

        $isExceeded = (float) $progress['spent_amount'] > (float) $progress['budget_amount'];
        $isWarning = ($progress['status'] ?? 'safe') === 'warning';

        if (! $isExceeded && ! $isWarning) {
            return;
        }

        $categoryName = $budget->category?->name ?? 'Uncategorized';
        $content = $isExceeded
            ? "Budget {$categoryName} terlampaui pada periode {$periodKey}."
            : "Budget {$categoryName} hampir habis pada periode {$periodKey}.";

        $persistenceService->saveInsight(
            userId: $this->userId,
            type: AIInsightType::BudgetAlert,
            periodKey: $periodKey,
            content: $content,
            metadata: [
                'budget_id' => $budget->id,
                'category' => $categoryName,
                'progress' => $progress,
            ]
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateBudgetInsightJob failed', [
            'user_id' => $this->userId,
            'budget_id' => $this->budgetId,
            'period_key' => $this->periodKey,
            'error' => $e->getMessage(),
        ]);
    }
}
