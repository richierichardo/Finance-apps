<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;

/**
 * Syncs wallet balance when transactions are created, updated, or deleted.
 * This listener replaces the former TransactionObserver for balance sync.
 * Fired by: TransactionCreated, TransactionUpdated, TransactionDeleted events.
 */
class UpdateWalletBalance
{
    public function handle(TransactionCreated|TransactionUpdated|TransactionDeleted $event): void
    {
        $transaction = $event->transaction;

        $transaction->load('wallet');
        $transaction->wallet->syncBalance();

        if ($transaction->reference_id) {
            $transaction->load('reference.wallet');
            $transaction->reference?->wallet?->syncBalance();
        }

        if ($event instanceof TransactionUpdated && $transaction->wasChanged('wallet_id')) {
            $oldWalletId = $transaction->getOriginal('wallet_id');
            if ($oldWalletId) {
                \App\Models\Wallet::find($oldWalletId)?->syncBalance();
            }
        }
    }
}
