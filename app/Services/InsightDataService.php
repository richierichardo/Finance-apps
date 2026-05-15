<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;

class InsightDataService
{
    public function __construct(
        protected BudgetService $budgetService,
    ) {}

    /**
     * Aggregate user financial data for a given period.
     *
     * @return array<string, mixed>
     */
    public function aggregateForPeriod(int $userId, string $periodKey): array
    {
        [$start, $end] = $this->getPeriodRange($periodKey);

        $baseQuery = Transaction::forUser($userId)
            ->excludingTransfers()
            ->whereBetween('occurred_at', [$start, $end]);

        // 2.1 total_income
        $totalIncome = (float) (clone $baseQuery)
            ->where('type', TransactionType::Income)
            ->sum('amount');

        // 2.2 total_expense
        $totalExpense = (float) (clone $baseQuery)
            ->where('type', TransactionType::Expense)
            ->sum('amount');

        // 2.3 net_cashflow
        $netCashflow = $totalIncome - $totalExpense;

        // 2.4 top_expense_categories
        $categoryRows = (clone $baseQuery)
            ->where('type', TransactionType::Expense)
            ->whereNotNull('category_transaction')
            ->selectRaw('category_transaction, SUM(amount) as total')
            ->groupBy('category_transaction')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $grandTotal = $categoryRows->sum('total');

        $topCategories = $categoryRows->map(function ($row) use ($grandTotal) {
            $total = (float) $row->total;

            return [
                'category_name' => $row->category_transaction,
                'total' => $total,
                'percentage' => $grandTotal > 0
                    ? round(($total / $grandTotal) * 100, 2)
                    : 0,
            ];
        })->values()->all();

        // 2.5 budget_usage
        $budgets = Budget::forUser($userId)->with('category')->get();

        $budgetUsage = $budgets->filter(function (Budget $budget) use ($start, $end) {
            return $budget->start_date <= $end
                && ($budget->end_date === null || $budget->end_date >= $start);
        })->map(function (Budget $budget) use ($userId, $start, $end) {
            $progress = $this->budgetService->getProgressForBudgetInPeriod($budget, $userId, $start, $end);
            $categoryName = $budget->category?->name ?? 'Uncategorized';

            return [
                'category' => $categoryName,
                'budget_amount' => (float) $progress['budget_amount'],
                'spent' => (float) $progress['spent_amount'],
                'remaining' => (float) $progress['remaining_amount'],
                'percentage' => (float) $progress['percentage_used'],
                'status' => $progress['status'],
            ];
        })->values()->all();

        // 2.6 recurring_transactions_in_period
        $recurringInPeriod = (clone $baseQuery)
            ->whereNotNull('recurring_transaction_id')
            ->orderBy('occurred_at')
            ->get(['id', 'amount', 'occurred_at', 'description', 'type'])
            ->map(fn ($t) => [
                'description' => $t->description ?? 'Recurring',
                'amount' => (float) $t->amount,
                'occurred_at' => $t->occurred_at?->format('Y-m-d'),
                'type' => $t->type->value ?? (string) $t->type,
            ])
            ->values()
            ->all();

        // 2.7 highest_spending_day
        $dailyRows = (clone $baseQuery)
            ->where('type', TransactionType::Expense)
            ->selectRaw('DATE(occurred_at) as date')
            ->selectRaw('SUM(amount) as total_expense')
            ->groupByRaw('DATE(occurred_at)')
            ->orderByDesc('total_expense')
            ->limit(1)
            ->get();

        $highestSpendingDay = null;

        if ($dailyRows->isNotEmpty()) {
            $row = $dailyRows->first();
            $highestSpendingDay = [
                'date' => (string) $row->date,
                'total_expense' => (float) $row->total_expense,
            ];
        }

        // 2.8 largest_transactions
        $largestTransactions = (clone $baseQuery)
            ->where('type', TransactionType::Expense)
            ->orderByDesc('amount')
            ->limit(5)
            ->get(['id', 'amount', 'description', 'occurred_at', 'category_transaction'])
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'amount' => (float) $t->amount,
                    'description' => $t->description ?? '-',
                    'occurred_at' => $t->occurred_at?->format('Y-m-d H:i'),
                    'category_name' => $t->category_transaction ?? 'Uncategorized',
                ];
            })
            ->values()
            ->all();

        return [
            'period_key' => $periodKey,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_cashflow' => $netCashflow,
            'top_expense_categories' => $topCategories,
            'budget_usage' => $budgetUsage,
            'recurring_transactions_in_period' => $recurringInPeriod,
            'highest_spending_day' => $highestSpendingDay,
            'largest_transactions' => $largestTransactions,
        ];
    }

    /**
     * Get [start, end] date range for a period key (YYYY-MM).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getPeriodRange(string $periodKey): array
    {
        $format = config('insights.period_key_format', 'Y-m');

        $start = Carbon::createFromFormat($format, $periodKey)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    /**
     * Get previous period key (e.g. 2025-02 -> 2025-01).
     */
    public function getPreviousPeriodKey(string $periodKey): string
    {
        $format = config('insights.period_key_format', 'Y-m');

        $current = Carbon::createFromFormat($format, $periodKey)->startOfMonth();
        $previous = $current->copy()->subMonth();

        return $previous->format($format);
    }
}
