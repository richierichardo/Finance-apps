<?php

namespace App\Services;

use App\Enums\AIInsightType;
use App\Models\AIInsight;

class AIInsightPersistenceService
{
    /**
     * Save or update insight content for a specific user, period, and type.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function saveInsight(
        int $userId,
        AIInsightType $type,
        string $periodKey,
        string $content,
        ?array $metadata = null
    ): AIInsight {
        return AIInsight::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'period_key' => $periodKey,
                'type' => $type->value,
            ],
            [
                'content' => $content,
                'metadata' => $metadata,
                'generated_at' => now(),
            ]
        );
    }
}
