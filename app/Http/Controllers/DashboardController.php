<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\BudgetService;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    private const CACHE_TTL_SECONDS = 600; // 10 minutes

    /**
     * Get [startOfMonth, endOfMonth] for current month.
     */
    private function currentMonthRange(): array
    {
        return [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ];
    }

    /**
     * Summary: total_income, total_expense, net_cashflow, wallet_balances (current month).
     */
    public function summary(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.summary.{$userId}";

        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId) {
            [$startOfMonth, $endOfMonth] = $this->currentMonthRange();

            $totalIncome = Transaction::forUser($userId)
                ->where('type', TransactionType::Income)
                ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $totalExpense = Transaction::forUser($userId)
                ->where('type', TransactionType::Expense)
                ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $walletBalances = Wallet::belongsToUser($userId)
                ->where('is_active', true)
                ->get(['id', 'name', 'balance'])
                ->map(fn ($w) => [
                    'wallet_id' => $w->id,
                    'wallet_name' => $w->name,
                    'balance' => (float) $w->balance,
                ])
                ->values()
                ->toArray();

            return [
                'total_income' => (float) $totalIncome,
                'total_expense' => (float) $totalExpense,
                'net_cashflow' => (float) ($totalIncome - $totalExpense),
                'wallet_balances' => $walletBalances,
            ];
        });

        return response()->json($data);
    }

    /**
     * Cashflow: aggregated income vs expense by daily or monthly.
     * Query param: period = daily | monthly (default: monthly).
     */
    public function cashflow(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'period' => ['nullable', 'string', 'in:daily,monthly'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $period = $request->query('period', 'monthly');
        $userId = auth()->id();
        $key = "dashboard.cashflow.{$userId}.{$period}";

        $rows = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId, $period) {
            $baseQuery = Transaction::forUser($userId)->excludingTransfers();

            if ($period === 'daily') {
                $startDate = Carbon::now()->subDays(30)->startOfDay();
                $baseQuery->where('occurred_at', '>=', $startDate);

                $rows = (clone $baseQuery)
                    ->selectRaw('DATE(occurred_at) as date')
                    ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income', [TransactionType::Income->value])
                    ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as expense', [TransactionType::Expense->value])
                    ->groupBy(DB::raw('DATE(occurred_at)'))
                    ->orderBy('date')
                    ->get();

                $rows = $rows->map(fn ($r) => [
                    'date' => Carbon::parse($r->date)->format('Y-m-d'),
                    'income' => (float) ($r->income ?? 0),
                    'expense' => (float) ($r->expense ?? 0),
                    'net' => (float) (($r->income ?? 0) - ($r->expense ?? 0)),
                ]);
            } else {
                $startDate = Carbon::now()->subMonths(12)->startOfMonth();
                $baseQuery->where('occurred_at', '>=', $startDate);

                $monthBucket = DB::getDriverName() === 'sqlite'
                    ? "strftime('%Y-%m-01', occurred_at)"
                    : "DATE_FORMAT(occurred_at, '%Y-%m-01')";

                $rows = (clone $baseQuery)
                    ->selectRaw("{$monthBucket} as date")
                    ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income', [TransactionType::Income->value])
                    ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as expense', [TransactionType::Expense->value])
                    ->groupBy(DB::raw($monthBucket))
                    ->orderBy('date')
                    ->get();

                $rows = $rows->map(fn ($r) => [
                    'date' => Carbon::parse($r->date)->format('Y-m-d'),
                    'income' => (float) ($r->income ?? 0),
                    'expense' => (float) ($r->expense ?? 0),
                    'net' => (float) (($r->income ?? 0) - ($r->expense ?? 0)),
                ]);
            }

            return $rows->values()->toArray();
        });

        return response()->json($rows);
    }

    /**
     * Category breakdown: expense grouped by category for current month.
     */
    public function categoryBreakdown(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.category-breakdown.{$userId}";

        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId) {
            [$startOfMonth, $endOfMonth] = $this->currentMonthRange();

            $rows = Transaction::forUser($userId)
                ->where('type', TransactionType::Expense)
                ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
                ->whereNotNull('category_transaction')
                ->selectRaw('category_transaction')
                ->selectRaw('SUM(amount) as total')
                ->groupBy('category_transaction')
                ->orderByDesc('total')
                ->get();

            $grandTotal = $rows->sum('total');

            $data = $rows->map(function ($r) use ($grandTotal) {
                $total = (float) $r->total;
                $categoryName = TransactionCategory::tryFrom($r->category_transaction)?->name ?? 'Uncategorized';

                return [
                    'category_name' => $categoryName,
                    'total' => $total,
                    'percentage' => $grandTotal > 0 ? round(($total / $grandTotal) * 100, 2) : 0,
                ];
            })->values()->toArray();

            return $data;
        });

        return response()->json($data);
    }

    /**
     * Wallet distribution: total balance per wallet (for pie/donut charts).
     */
    public function walletDistribution(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.wallet-distribution.{$userId}";

        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId) {
            return Wallet::belongsToUser($userId)
                ->where('is_active', true)
                ->get(['id', 'name', 'balance'])
                ->map(fn ($w) => [
                    'wallet_id' => $w->id,
                    'wallet_name' => $w->name,
                    'balance' => (float) $w->balance,
                ])
                ->values()
                ->toArray();
        });

        return response()->json($data);
    }

    /**
     * Daily expense: expense grouped by date for last 30 days.
     */
    public function dailyExpense(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.daily-expense.{$userId}";

        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId) {
            $startDate = Carbon::now()->subDays(30)->startOfDay();

            $rows = Transaction::forUser($userId)
                ->where('type', TransactionType::Expense)
                ->where('occurred_at', '>=', $startDate)
                ->selectRaw('DATE(occurred_at) as date')
                ->selectRaw('SUM(amount) as total_expense')
                ->groupBy(DB::raw('DATE(occurred_at)'))
                ->orderBy('date')
                ->get();

            return $rows->map(fn ($r) => [
                'date' => Carbon::parse($r->date)->format('Y-m-d'),
                'total_expense' => (float) $r->total_expense,
            ])->values()->toArray();
        });

        return response()->json($data);
    }

    /**
     * Top 5 highest expense transactions this month.
     */
    public function topExpenses(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.top-expenses.{$userId}";

        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId) {
            [$startOfMonth, $endOfMonth] = $this->currentMonthRange();

            $transactions = Transaction::forUser($userId)
                ->where('type', TransactionType::Expense)
                ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
                ->with('wallet:id,name')
                ->orderByDesc('amount')
                ->limit(5)
                ->get(['id', 'amount', 'description', 'occurred_at', 'category_transaction', 'wallet_id']);

            return $transactions->map(fn ($t) => [
                'id' => $t->id,
                'amount' => (float) $t->amount,
                'description' => $t->description ?? '-',
                'occurred_at' => $t->occurred_at->format('Y-m-d H:i'),
                'category_name' => TransactionCategory::tryFrom($t->category_transaction)?->name ?? 'Uncategorized',
                'wallet_name' => $t->wallet?->name ?? '-',
            ])->values()->toArray();
        });

        return response()->json($data);
    }

    /**
     * Budget progress: per-budget spent, remaining, percentage, status.
     */
    public function budgets(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.budgets.{$userId}";

        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($userId) {
            $budgetService = app(BudgetService::class);

            $budgets = Budget::forUser($userId)
                ->with('category')
                ->get();

            return $budgets->map(function (Budget $budget) use ($budgetService, $userId) {
                $progress = $budgetService->getProgressForBudget($budget, $userId);
                $categoryName = $budget->category?->name ?? 'Uncategorized';

                return [
                    'budget_id' => $budget->id,
                    'category_id' => $budget->category_id,
                    'category' => $categoryName,
                    'budget_amount' => (float) $progress['budget_amount'],
                    'spent' => (float) $progress['spent_amount'],
                    'remaining' => (float) $progress['remaining_amount'],
                    'percentage' => (int) round($progress['percentage_used']),
                    'status' => $progress['status'],
                ];
            })->values()->toArray();
        });

        return response()->json($data);
    }

    /**
     * Upcoming recurring: next 5 items for dashboard widget.
     */
    public function upcomingRecurring(): JsonResponse
    {
        $userId = auth()->id();
        $key = "dashboard.upcoming_recurring.{$userId}";
        $ttl = 300; // 5 minutes

        $data = Cache::remember($key, $ttl, function () use ($userId) {
            $service = app(RecurringTransactionService::class);

            return $service->getUpcomingForUser($userId, 5);
        });

        return response()->json($data);
    }
}
