<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use Illuminate\Support\Facades\Cache;

class InvalidateDashboardCache
{
    public function handle(TransactionCreated|TransactionUpdated|TransactionDeleted $event): void
    {
        $userId = $event->transaction->user_id;

        $keys = [
            "dashboard.summary.{$userId}",
            "dashboard.cashflow.{$userId}.daily",
            "dashboard.cashflow.{$userId}.monthly",
            "dashboard.category-breakdown.{$userId}",
            "dashboard.wallet-distribution.{$userId}",
            "dashboard.daily-expense.{$userId}",
            "dashboard.top-expenses.{$userId}",
            "dashboard.budgets.{$userId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
