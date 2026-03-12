<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'amount',
        'category_transaction',
        'description',
        'source',
        'reference_id',
        'recurring_transaction_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'category_transaction' => 'string',
            'source' => TransactionSource::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope: filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: exclude transfer transactions.
     */
    public function scopeExcludingTransfers(Builder $query): Builder
    {
        return $query->whereNotIn('type', [
            TransactionType::TransferOut,
            TransactionType::TransferIn,
        ]);
    }

    /**
     * Transaksi pasangan untuk transfer (transfer_out ↔ transfer_in).
     */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reference_id');
    }

    /**
     * Recurring template that generated this transaction (if any).
     */
    public function recurring(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_transaction_id');
    }
}
