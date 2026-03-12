<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\Wallet;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->syncWalletBalance($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->syncWalletBalance($transaction);

        if ($transaction->wasChanged('wallet_id')) {
            $oldWalletId = $transaction->getOriginal('wallet_id');
            if ($oldWalletId) {
                Wallet::find($oldWalletId)?->syncBalance();
            }
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->syncWalletBalance($transaction);
    }

    protected function syncWalletBalance(Transaction $transaction): void
    {
        $transaction->load('wallet');
        $transaction->wallet->syncBalance();

        if ($transaction->reference_id) {
            $transaction->load('reference.wallet');
            $transaction->reference?->wallet?->syncBalance();
        }
    }
}
