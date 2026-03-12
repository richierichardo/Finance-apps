<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;

class BudgetService
{
    /**
     * Get budget progress: spent amount, remaining, percentage, status.
     *
     * @return array{budget_amount: float, spent_amount: float, remaining_amount: float, percentage_used: float, status: string}
     */
    public function getProgressForBudget(Budget $budget, int $userId): array
    {
        [$start, $end] = $this->getPeriodRange($budget->period);

        return $this->getProgressForBudgetInPeriod($budget, $userId, $start, $end);
    }

    /**
     * Get budget progress for an arbitrary date range.
     * Used by InsightDataService for period-based aggregation.
     *
     * @return array{budget_amount: float, spent_amount: float, remaining_amount: float, percentage_used: float, status: string}
     */
    public function getProgressForBudgetInPeriod(Budget $budget, int $userId, Carbon $start, Carbon $end): array
    {
        $budget->load('category');
        $categorySlug = $budget->category?->slug;

        $budgetAmount = (float) $budget->amount;

        $spentAmount = 0.0;

        if ($categorySlug) {
            $spentAmount = (float) Transaction::forUser($userId)
                ->where('type', TransactionType::Expense)
                ->where('category_transaction', $categorySlug)
                ->whereBetween('occurred_at', [$start, $end])
                ->sum('amount');
        }

        $remainingAmount = max(0, $budgetAmount - $spentAmount);

        $percentageUsed = 0.0;
        if ($budgetAmount > 0) {
            $percentageUsed = (float) min(100, round(($spentAmount / $budgetAmount) * 100, 2));
        }

        $status = $this->getStatus($percentageUsed);

        return [
            'budget_amount' => $budgetAmount,
            'spent_amount' => $spentAmount,
            'remaining_amount' => $remainingAmount,
            'percentage_used' => $percentageUsed,
            'status' => $status,
        ];
    }

    /**
     * Get status based on percentage used.
     * safe: <70%, warning: 70-100%, exceeded: >100%
     */
    public function getStatus(float $percentage): string
    {
        if ($percentage < 70) {
            return 'safe';
        }
        if ($percentage <= 100) {
            return 'warning';
        }
        return 'exceeded';
    }

    /**
     * Get [start, end] for current period based on monthly or weekly.
     *
     * @return array{\Carbon\Carbon, \Carbon\Carbon}
     */
    private function getPeriodRange(string $period): array
    {
        $now = Carbon::now();

        if ($period === 'weekly') {
            return [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ];
        }

        return [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ];
    }
}
