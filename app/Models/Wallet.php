<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'initial_balance',
        'balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
            'initial_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * Scope untuk filter wallet milik user tertentu.
     */
    public function scopeBelongsToUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Hitung balance dari transaksi (source of truth).
     * Formula: SUM(income, transfer_in) - SUM(expense, transfer_out).
     * initial_balance diwakili oleh adjustment transaction (Income) saat wallet dibuat.
     */
    public function calculateBalance(): float
    {
        $credits = $this->transactions()
            ->whereIn('type', [TransactionType::Income, TransactionType::TransferIn])
            ->sum('amount');

        $debits = $this->transactions()
            ->whereIn('type', [TransactionType::Expense, TransactionType::TransferOut])
            ->sum('amount');

        return (float) ($credits - $debits);
    }

    /**
     * Sync cached balance.
     */
    public function syncBalance(): void
    {
        $this->update(['balance' => $this->calculateBalance()]);
    }
}
