<?php

namespace App\Services;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecurringTransactionService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Process all due recurring transactions and create actual transactions.
     * Returns count of transactions created.
     */
    public function processDueRecurringTransactions(): int
    {
        $recurring = \App\Models\RecurringTransaction::query()
            ->active()
            ->due()
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', today());
            })
            ->with(['wallet', 'category', 'user'])
            ->get();

        $created = 0;

        foreach ($recurring as $item) {
            try {
                $this->processOneRecurring($item);
                $created++;
            } catch (\Throwable $e) {
                Log::error('RecurringTransaction processing failed', [
                    'recurring_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Process a single recurring transaction: create transaction and update next_run_at.
     */
    protected function processOneRecurring(\App\Models\RecurringTransaction $recurring): void
    {
        $wallet = $recurring->wallet;
        $category = $recurring->category;

        if (! $wallet || ! $category) {
            return;
        }

        if ($wallet->user_id !== $recurring->user_id) {
            return;
        }

        if ((float) $recurring->amount <= 0) {
            return;
        }

        if ($recurring->end_date && $recurring->end_date->isPast()) {
            $recurring->update(['is_active' => false]);
            return;
        }

        $type = $recurring->type === 'income'
            ? TransactionType::Income
            : TransactionType::Expense;

        $categorySlug = $category->slug;
        if (! \App\Enums\TransactionCategory::tryFrom($categorySlug)) {
            Log::warning('RecurringTransaction category slug not in TransactionCategory enum', [
                'recurring_id' => $recurring->id,
                'slug' => $categorySlug,
            ]);
            $categorySlug = 'other';
        }

        $this->transactionService->create([
            'user_id' => $recurring->user_id,
            'wallet_id' => $recurring->wallet_id,
            'category_transaction' => $categorySlug,
            'type' => $type,
            'amount' => $recurring->amount,
            'description' => $recurring->description ?? 'Recurring',
            'occurred_at' => now(),
            'source' => TransactionSource::System,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $nextRun = $this->calculateNextRunAt($recurring);
        $recurring->next_run_at = $nextRun;

        if ($recurring->end_date && $nextRun->isAfter($recurring->end_date)) {
            $recurring->is_active = false;
        }

        $recurring->save();

        Cache::forget('dashboard.upcoming_recurring.' . $recurring->user_id);
    }

    protected function calculateNextRunAt(\App\Models\RecurringTransaction $recurring): Carbon
    {
        $current = Carbon::parse($recurring->next_run_at);
        $interval = max(1, (int) $recurring->interval);

        return match ($recurring->frequency) {
            'daily' => $current->addDays($interval),
            'weekly' => $current->addWeeks($interval),
            'monthly' => $current->addMonths($interval),
            default => $current->addDay(),
        };
    }

    /**
     * Get upcoming recurring transactions for dashboard widget.
     *
     * @return array<int, array{description: string, amount: float, next_run_at: string}>
     */
    public function getUpcomingForUser(int $userId, int $limit = 5): array
    {
        return \App\Models\RecurringTransaction::query()
            ->forUser($userId)
            ->active()
            ->where('next_run_at', '>=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', today());
            })
            ->orderBy('next_run_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'description' => $r->description ?? 'Recurring',
                'amount' => (float) $r->amount,
                'next_run_at' => $r->next_run_at->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }
}
