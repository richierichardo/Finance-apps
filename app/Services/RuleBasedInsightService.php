<?php

namespace App\Services;

class RuleBasedInsightService
{
    private const TYPE_BUDGET_EXCEEDED = 'budget_exceeded';

    private const TYPE_BUDGET_WARNING = 'budget_warning';

    private const TYPE_SPENDING_INCREASE = 'spending_increase';

    private const TYPE_TOP_SPENDING_CATEGORY = 'top_spending_category';

    private const TYPE_UNUSUALLY_LARGE_TRANSACTION = 'unusually_large_transaction';

    private const TYPE_HIGHEST_SPENDING_DAY = 'highest_spending_day';

    private const SEVERITY_INFO = 'info';

    private const SEVERITY_WARNING = 'warning';

    private const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        protected InsightDataService $insightDataService,
    ) {}

    /**
     * Generate deterministic insights for a user and period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateInsights(int $userId, string $periodKey): array
    {
        $current = $this->insightDataService->aggregateForPeriod($userId, $periodKey);

        $previous = null;
        try {
            $previousKey = $this->insightDataService->getPreviousPeriodKey($periodKey);
            $previous = $this->insightDataService->aggregateForPeriod($userId, $previousKey);
        } catch (\Throwable) {
            $previous = null;
        }

        $insights = [];

        $this->applyBudgetRules($current, $insights);
        $this->applySpendingIncreaseRule($current, $previous, $insights);
        $this->applyTopCategoryRule($current, $insights);
        $this->applyLargeTransactionRule($current, $insights);
        $this->applyHighestSpendingDayRule($current, $insights);

        return $insights;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<int, array<string, mixed>>  $insights
     */
    private function applyBudgetRules(array $current, array &$insights): void
    {
        $budgets = $current['budget_usage'] ?? [];

        if (empty($budgets)) {
            return;
        }

        // Budget exceeded
        foreach ($budgets as $budget) {
            $status = $budget['status'] ?? 'safe';
            $category = $budget['category'] ?? 'Unknown';

            if ($status === 'exceeded') {
                $insights[] = $this->makeInsight(
                    self::TYPE_BUDGET_EXCEEDED,
                    self::SEVERITY_CRITICAL,
                    "Budget terlampaui untuk {$category}",
                    "Pengeluaran kamu untuk kategori {$category} sudah melebihi budget yang ditetapkan.",
                    ['category' => $category, 'budget' => $budget],
                );
            }
        }

        // Budget warning
        $threshold = (float) config('insights.budget_warning_threshold', 70);

        foreach ($budgets as $budget) {
            $status = $budget['status'] ?? 'safe';
            $category = $budget['category'] ?? 'Unknown';
            $percentage = (float) ($budget['percentage'] ?? 0);

            if ($status === 'warning' || $percentage >= $threshold) {
                $insights[] = $this->makeInsight(
                    self::TYPE_BUDGET_WARNING,
                    self::SEVERITY_WARNING,
                    "Budget hampir habis untuk {$category}",
                    "Pengeluaran di kategori {$category} sudah mencapai sekitar {$percentage}% dari budget.",
                    ['category' => $category, 'budget' => $budget],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $previous
     * @param  array<int, array<string, mixed>>  $insights
     */
    private function applySpendingIncreaseRule(?array $current, ?array $previous, array &$insights): void
    {
        if (! $current || ! $previous) {
            return;
        }

        $currentExpense = (float) ($current['total_expense'] ?? 0);
        $previousExpense = (float) ($previous['total_expense'] ?? 0);

        if ($previousExpense <= 0 || $currentExpense <= 0) {
            return;
        }

        $delta = $currentExpense - $previousExpense;
        if ($delta <= 0) {
            return;
        }

        $increasePercent = ($delta / $previousExpense) * 100;
        $threshold = (float) config('insights.spending_increase_threshold_percent', 20);

        if ($increasePercent >= $threshold) {
            $insights[] = $this->makeInsight(
                self::TYPE_SPENDING_INCREASE,
                self::SEVERITY_WARNING,
                'Pengeluaran naik signifikan dibanding bulan sebelumnya',
                sprintf(
                    'Total pengeluaran kamu naik sekitar %.1f%% dibanding periode sebelumnya.',
                    $increasePercent,
                ),
                [
                    'current_expense' => $currentExpense,
                    'previous_expense' => $previousExpense,
                    'increase_percent' => $increasePercent,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<int, array<string, mixed>>  $insights
     */
    private function applyTopCategoryRule(array $current, array &$insights): void
    {
        $categories = $current['top_expense_categories'] ?? [];

        if (empty($categories)) {
            return;
        }

        $top = $categories[0];
        $name = $top['category_name'] ?? 'Unknown';
        $percentage = (float) ($top['percentage'] ?? 0);

        $insights[] = $this->makeInsight(
            self::TYPE_TOP_SPENDING_CATEGORY,
            self::SEVERITY_INFO,
            "Kategori pengeluaran terbesar: {$name}",
            "Kategori {$name} menyumbang sekitar {$percentage}% dari total pengeluaran periode ini.",
            ['category' => $top],
        );
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<int, array<string, mixed>>  $insights
     */
    private function applyLargeTransactionRule(array $current, array &$insights): void
    {
        $transactions = $current['largest_transactions'] ?? [];
        $totalExpense = (float) ($current['total_expense'] ?? 0);

        if ($totalExpense <= 0 || empty($transactions)) {
            return;
        }

        $thresholdPercent = (float) config('insights.large_transaction_threshold_percent', 20);

        foreach ($transactions as $tx) {
            $amount = (float) ($tx['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $percent = ($amount / $totalExpense) * 100;
            if ($percent < $thresholdPercent) {
                continue;
            }

            $severity = $percent >= ($thresholdPercent * 2)
                ? self::SEVERITY_WARNING
                : self::SEVERITY_INFO;

            $description = sprintf(
                'Ada transaksi sebesar %s yang menyumbang sekitar %.1f%% dari total pengeluaran periode ini.',
                number_format($amount, 0, ',', '.'),
                $percent,
            );

            $insights[] = $this->makeInsight(
                self::TYPE_UNUSUALLY_LARGE_TRANSACTION,
                $severity,
                'Transaksi besar yang tidak biasa',
                $description,
                ['transaction' => $tx, 'percent_of_total' => $percent],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<int, array<string, mixed>>  $insights
     */
    private function applyHighestSpendingDayRule(array $current, array &$insights): void
    {
        $day = $current['highest_spending_day'] ?? null;
        if (! $day) {
            return;
        }

        $date = $day['date'] ?? null;
        $total = (float) ($day['total_expense'] ?? 0);

        if (! $date || $total <= 0) {
            return;
        }

        $insights[] = $this->makeInsight(
            self::TYPE_HIGHEST_SPENDING_DAY,
            self::SEVERITY_INFO,
            'Hari dengan pengeluaran tertinggi',
            "Hari {$date} adalah hari dengan pengeluaran tertinggi, sekitar ".number_format($total, 0, ',', '.').'.',
            ['day' => $day],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function makeInsight(string $type, string $severity, string $title, string $description, array $metadata = []): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
        ];
    }
}
