<?php

namespace App\Observers;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;

class WalletObserver
{
    /**
     * Handle the Wallet "created" event.
     * Buat adjustment transaction jika initial_balance > 0.
     */
    public function created(Wallet $wallet): void
    {
        if ($wallet->initial_balance > 0) {
            Transaction::create([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => TransactionType::Income,
                'amount' => $wallet->initial_balance,
                'description' => 'Initial balance',
                'source' => TransactionSource::Web,
                'occurred_at' => now(),
            ]);
        }
    }

    /**
     * Handle the Wallet "updated" event.
     */
    public function updated(Wallet $wallet): void
    {
        //
    }

    /**
     * Handle the Wallet "deleted" event.
     */
    public function deleted(Wallet $wallet): void
    {
        //
    }

    /**
     * Handle the Wallet "restored" event.
     */
    public function restored(Wallet $wallet): void
    {
        //
    }

    /**
     * Handle the Wallet "force deleted" event.
     */
    public function forceDeleted(Wallet $wallet): void
    {
        //
    }
}
