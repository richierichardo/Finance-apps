<?php

namespace App\Services\AI;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\ForecastInsightService;
use App\Services\InsightDataService;
use Carbon\Carbon;

class FinanceAIContextService
{
    public function __construct(
        protected InsightDataService $insightDataService,
        protected ForecastInsightService $forecastInsightService,
    ) {}

    public function currentPeriodKey(): string
    {
        return now()->format(config('insights.period_key_format', 'Y-m'));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboardContext(int $userId, ?string $periodKey = null): array
    {
        $periodKey = $periodKey ?? $this->currentPeriodKey();
        $aggregated = $this->insightDataService->aggregateForPeriod($userId, $periodKey);

        return [
            'period_key' => $periodKey,
            'currency' => 'IDR',
            'summary' => [
                'total_income' => (float) ($aggregated['total_income'] ?? 0),
                'total_expense' => (float) ($aggregated['total_expense'] ?? 0),
                'net_cashflow' => (float) ($aggregated['net_cashflow'] ?? 0),
                'total_balance' => $this->totalWalletBalance($userId),
            ],
            'wallets' => $this->walletList($userId),
            'recent_transactions' => $this->recentTransactions($userId),
            'budgets' => $aggregated['budget_usage'] ?? [],
            'top_expense_categories' => $aggregated['top_expense_categories'] ?? [],
            'forecast' => $this->forecastInsightService->generateForecastInsight($userId, $periodKey),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug'])->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildWalletContext(int $userId): array
    {
        return [
            'wallets' => $this->walletList($userId),
            'summary' => [
                'total_balance' => $this->totalWalletBalance($userId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTransactionContext(int $userId, ?string $periodKey = null): array
    {
        $periodKey = $periodKey ?? $this->currentPeriodKey();
        $aggregated = $this->insightDataService->aggregateForPeriod($userId, $periodKey);

        return [
            'period_key' => $periodKey,
            'summary' => [
                'total_income' => (float) ($aggregated['total_income'] ?? 0),
                'total_expense' => (float) ($aggregated['total_expense'] ?? 0),
                'net_cashflow' => (float) ($aggregated['net_cashflow'] ?? 0),
            ],
            'top_expense_categories' => $aggregated['top_expense_categories'] ?? [],
            'recent_transactions' => $this->recentTransactions($userId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildBudgetContext(int $userId, ?string $periodKey = null): array
    {
        $periodKey = $periodKey ?? $this->currentPeriodKey();
        $aggregated = $this->insightDataService->aggregateForPeriod($userId, $periodKey);

        return [
            'period_key' => $periodKey,
            'budgets' => $aggregated['budget_usage'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForecastContext(int $userId, ?string $periodKey = null): array
    {
        $periodKey = $periodKey ?? $this->currentPeriodKey();

        return [
            'period_key' => $periodKey,
            'forecast' => $this->forecastInsightService->generateForecastInsight($userId, $periodKey),
        ];
    }

    private function totalWalletBalance(int $userId): float
    {
        return (float) Wallet::belongsToUser($userId)->sum('balance');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function walletList(int $userId): array
    {
        return Wallet::belongsToUser($userId)
            ->get(['id', 'name', 'type', 'balance'])
            ->map(fn (Wallet $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'type' => $w->type?->value ?? (string) $w->type,
                'balance' => (float) $w->balance,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTransactions(int $userId, int $limit = 20): array
    {
        return Transaction::forUser($userId)
            ->with('wallet:id,name')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'type' => $t->type?->value ?? (string) $t->type,
                'wallet' => $t->wallet?->name,
                'category' => $t->category_transaction,
                'amount' => (float) $t->amount,
                'description' => $t->description,
                'date' => $t->occurred_at?->format('Y-m-d H:i'),
            ])
            ->all();
    }

    public function matchWalletName(?string $walletName, array $context): ?string
    {
        if (! $walletName) {
            return null;
        }

        $userId = (int) ($context['user_id'] ?? 0);
        $resolver = app(FinanceEntityResolverService::class);
        $resolved = $resolver->resolveWallet($userId, $walletName, $context);

        return $resolved['name'] ?? null;
    }

    public function resolveWalletId(int $userId, ?string $walletName, array $context): ?int
    {
        $matched = $this->matchWalletName($walletName, $context);
        if (! $matched) {
            return null;
        }

        foreach ($context['wallets'] ?? [] as $wallet) {
            if ($wallet['name'] === $matched) {
                return (int) $wallet['id'];
            }
        }

        return null;
    }

    public function resolveCategoryId(?string $categoryName): ?int
    {
        if (! $categoryName) {
            return null;
        }

        $category = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($categoryName))])
            ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower(trim($categoryName))])
            ->first();

        return $category?->id;
    }

    public function resolveCategoryTransactionEnum(?string $categoryName, string $transactionType): ?string
    {
        if (! $categoryName) {
            return null;
        }

        $normalized = mb_strtolower(str_replace([' ', '-'], '_', trim($categoryName)));

        $incomeValues = array_column(\App\Enums\TransactionCategoryIncome::cases(), 'value');
        $expenseValues = array_column(\App\Enums\TransactionCategoryExpenses::cases(), 'value');

        $pool = $transactionType === 'income' ? $incomeValues : $expenseValues;

        foreach ($pool as $value) {
            if ($value === $normalized || str_contains($value, $normalized)) {
                return $value;
            }
        }

        return null;
    }

    public function parseOccurredAt(?string $date, ?string $time): string
    {
        if ($date) {
            $timePart = $time ? substr(preg_replace('/\D/', '', $time).'00', 0, 4) : '0000';
            $hours = (int) substr($timePart, 0, 2);
            $minutes = (int) substr($timePart, 2, 2);

            return Carbon::parse($date)->setTime($hours, $minutes)->toDateTimeString();
        }

        return now()->toDateTimeString();
    }
}
