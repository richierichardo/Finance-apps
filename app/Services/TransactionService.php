<?php

namespace App\Services;

use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Models\Transaction;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class TransactionService
{
    /**
     * Create a new transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Transaction
    {
        if (isset($data['amount']) && (float) $data['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be greater than 0.');
        }

        $transaction = Transaction::create($data);
        Event::dispatch(new TransactionCreated($transaction));

        return $transaction;
    }

    /**
     * Update an existing transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        if (isset($data['amount']) && (float) $data['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be greater than 0.');
        }

        $transaction->update($data);
        Event::dispatch(new TransactionUpdated($transaction->fresh()));

        return $transaction->fresh();
    }

    /**
     * Delete a transaction.
     */
    public function delete(Transaction $transaction): bool
    {
        $transaction->load('wallet', 'reference');
        Event::dispatch(new TransactionDeleted($transaction));

        return $transaction->delete();
    }
}
