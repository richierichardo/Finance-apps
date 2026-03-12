<?php

namespace App\Services;

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
        $wallet->update($data);

        return $wallet->fresh();
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
