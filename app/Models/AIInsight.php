<?php

namespace App\Models;

use App\Enums\AIInsightType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIInsight extends Model
{
    use HasFactory;

    protected $table = 'ai_insights';

    protected $fillable = [
        'user_id',
        'type',
        'period_key',
        'content',
        'metadata',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AIInsightType::class,
            'metadata' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod(Builder $query, string $periodKey): Builder
    {
        return $query->where('period_key', $periodKey);
    }

    public function scopeOfType(Builder $query, AIInsightType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public static function findLatestForUser(int $userId, string $periodKey, AIInsightType $type): ?self
    {
        return self::query()
            ->forUser($userId)
            ->forPeriod($periodKey)
            ->ofType($type)
            ->latest('generated_at')
            ->first();
    }

    public static function findLatestMonthly(int $userId, string $periodKey): ?self
    {
        return self::findLatestForUser($userId, $periodKey, AIInsightType::MonthlySummary);
    }
}
