<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;

class ForecastInsightService
{
    public function __construct(
        protected InsightDataService $insightDataService,
    ) {}

    /**
     * Generate forecast insight for a period.
     * Future: use historical data + optional ML to project income/expense.
     *
     * @return array{
     *   forecast_available: bool,
     *   projected_income?: float,
     *   projected_expense?: float,
     *   projected_net?: float,
     *   confidence?: float,
     *   message?: string
     * }
     */
    public function generateForecastInsight(int $userId, string $periodKey): array
    {
        $format = config('insights.period_key_format', 'Y-m');
        $base = Carbon::createFromFormat($format, $periodKey)->startOfMonth();

        $periodKeys = [
            $base->copy()->subMonth(1)->format($format),
            $base->copy()->subMonth(2)->format($format),
            $base->copy()->subMonth(3)->format($format),
        ];

        $samples = [];
        foreach ($periodKeys as $key) {
            $samples[] = $this->insightDataService->aggregateForPeriod($userId, $key);
        }

        $count = count($samples);
        if ($count === 0) {
            return [
                'forecast_available' => false,
                'message' => 'Forecasting coming soon',
            ];
        }

        $incomeSum = array_sum(array_map(
            fn (array $item): float => (float) ($item['total_income'] ?? 0),
            $samples
        ));
        $expenseSum = array_sum(array_map(
            fn (array $item): float => (float) ($item['total_expense'] ?? 0),
            $samples
        ));

        $projectedIncome = $incomeSum / $count;
        $projectedExpense = $expenseSum / $count;

        return [
            'forecast_available' => true,
            'projected_income' => round($projectedIncome, 2),
            'projected_expense' => round($projectedExpense, 2),
            'projected_net' => round($projectedIncome - $projectedExpense, 2),
            'confidence' => 0.5,
            'message' => 'Beta forecast based on 3-month average.',
        ];
    }

    /**
     * Predict if budget will be overrun by end of period.
     * Future: extrapolate spending rate, seasonal factors.
     *
     * @return array{
     *   will_overrun: bool,
     *   projected_spend: float,
     *   confidence: float,
     *   days_remaining: int,
     *   message: string
     * }|null
     */
    public function predictBudgetOverrun(int $userId, int $categoryId, ?string $periodKey = null): ?array
    {
        $period = $periodKey ?: now()->format(config('insights.period_key_format', 'Y-m'));
        [$start, $end] = $this->insightDataService->getPeriodRange($period);

        $budget = Budget::query()
            ->with('category')
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->whereDate('start_date', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start);
            })
            ->first();

        if (! $budget || ! $budget->category?->slug) {
            return null;
        }

        $spent = (float) Transaction::query()
            ->forUser($userId)
            ->where('type', TransactionType::Expense)
            ->where('category_transaction', $budget->category->slug)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        $daysElapsed = max(1, $start->diffInDays(now()->min($end)) + 1);
        $daysRemaining = max(0, now()->min($end)->diffInDays($end));
        $dailyRate = $spent / $daysElapsed;
        $projectedSpend = $spent + ($dailyRate * $daysRemaining);
        $budgetAmount = (float) $budget->amount;

        return [
            'will_overrun' => $projectedSpend > $budgetAmount,
            'projected_spend' => round($projectedSpend, 2),
            'confidence' => 0.5,
            'days_remaining' => (int) $daysRemaining,
            'message' => 'Approximate projection from current spending rate.',
        ];
    }
}
