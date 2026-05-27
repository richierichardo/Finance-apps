<?php

namespace App\Services;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}
    /**
     * Execute a transfer between two wallets.
     *
     * @return array{0: Transaction, 1: Transaction} [transferOut, transferIn]
     *
     * @throws InvalidArgumentException
     */
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        ?string $description,
        string $occurredAt,
        TransactionSource|string $source = TransactionSource::WebManual,
    ): array {
        $sourceValue = $source instanceof TransactionSource ? $source->value : $source;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than 0.');
        }

        if ($fromWallet->user_id !== $toWallet->user_id) {
            throw new InvalidArgumentException('Cannot transfer between wallets of different users.');
        }

        if (! $fromWallet->is_active || ! $toWallet->is_active) {
            throw new InvalidArgumentException('Both wallets must be active for transfer.');
        }

        if ((float) $fromWallet->balance < $amount) {
            throw new InvalidArgumentException('Insufficient balance in source wallet.');
        }

        return DB::transaction(function () use ($fromWallet, $toWallet, $amount, $description, $occurredAt, $sourceValue) {
            $transferOut = $this->transactionService->create([
                'user_id' => $fromWallet->user_id,
                'wallet_id' => $fromWallet->id,
                'type' => TransactionType::TransferOut,
                'amount' => $amount,
                'description' => $description ?? 'Transfer',
                'source' => $sourceValue,
                'occurred_at' => $occurredAt,
            ]);

            $transferIn = $this->transactionService->create([
                'user_id' => $toWallet->user_id,
                'wallet_id' => $toWallet->id,
                'type' => TransactionType::TransferIn,
                'amount' => $amount,
                'description' => $description ?? 'Transfer',
                'source' => $sourceValue,
                'reference_id' => $transferOut->id,
                'occurred_at' => $occurredAt,
            ]);

            $this->transactionService->update($transferOut, ['reference_id' => $transferIn->id]);

            return [$transferOut->fresh(), $transferIn->fresh()];
        });
    }
}
