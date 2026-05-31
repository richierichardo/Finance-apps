<?php

namespace App\Services;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;

class WalletService
{
    /**
     * Create a new wallet.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Wallet
    {
        return Wallet::create($data);
    }

    /**
     * Update an existing wallet.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Wallet $wallet, array $data): Wallet
    {
        $initialBalanceChanged = array_key_exists('initial_balance', $data)
            && (float) ($data['initial_balance'] ?? 0) !== (float) $wallet->initial_balance;

        $wallet->update($data);

        if ($initialBalanceChanged) {
            $this->syncInitialBalanceTransaction($wallet->fresh(), (float) $wallet->initial_balance);
        }

        return $wallet->fresh();
    }

    /**
     * Keep the "Initial balance" income transaction in sync with wallet.initial_balance.
     */
    protected function syncInitialBalanceTransaction(Wallet $wallet, float $amount): void
    {
        $existing = $wallet->transactions()
            ->where('type', TransactionType::Income)
            ->where('description', 'Initial balance')
            ->first();

        if ($amount <= 0) {
            $existing?->delete();
            $wallet->syncBalance();

            return;
        }

        if ($existing) {
            $existing->update(['amount' => $amount]);
            $wallet->syncBalance();

            return;
        }

        Transaction::create([
            'user_id' => $wallet->user_id,
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Income,
            'amount' => $amount,
            'description' => 'Initial balance',
            'source' => TransactionSource::SystemInitialBalance,
            'occurred_at' => now(),
        ]);

        $wallet->syncBalance();
    }

    /**
     * Delete a wallet.
     */
    public function delete(Wallet $wallet): bool
    {
        return $wallet->delete();
    }

    /**
     * Recalculate and update cached balance for a wallet.
     */
    public function syncBalance(Wallet $wallet): void
    {
        $wallet->syncBalance();
    }
}
