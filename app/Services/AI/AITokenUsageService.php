<?php

namespace App\Services\AI;

use App\Models\AiMessage;
use App\Models\AiTrainingExample;

class AITokenUsageService
{
    /**
     * @param  array<string, mixed>|null  $rawUsage
     * @return array{prompt_tokens: int|null, completion_tokens: int|null, total_tokens: int|null, raw: array|null}
     */
    public function normalizeUsage(?array $rawUsage): array
    {
        if ($rawUsage === null || $rawUsage === []) {
            return [
                'prompt_tokens' => null,
                'completion_tokens' => null,
                'total_tokens' => null,
                'raw' => null,
            ];
        }

        $prompt = $rawUsage['prompt_tokens']
            ?? $rawUsage['input_tokens']
            ?? null;
        $completion = $rawUsage['completion_tokens']
            ?? $rawUsage['output_tokens']
            ?? null;
        $total = $rawUsage['total_tokens'] ?? null;

        if ($total === null && $prompt !== null && $completion !== null) {
            $total = (int) $prompt + (int) $completion;
        }

        return [
            'prompt_tokens' => $prompt !== null ? (int) $prompt : null,
            'completion_tokens' => $completion !== null ? (int) $completion : null,
            'total_tokens' => $total !== null ? (int) $total : null,
            'raw' => $rawUsage,
        ];
    }

    /**
     * @param  array<string, mixed>  $aiResult
     */
    public function attachToMessage(AiMessage $message, array $aiResult): void
    {
        $usage = $this->normalizeUsage($aiResult['usage'] ?? null);

        $message->update([
            'token_usage' => $usage,
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'provider' => $aiResult['provider'] ?? config('ai.provider'),
            'model' => $aiResult['model'] ?? config('ai.model'),
            'latency_ms' => $aiResult['latency_ms'] ?? $message->latency_ms,
        ]);
    }

    /**
     * @param  array<string, mixed>  $aiResult
     */
    public function attachToTrainingExample(AiTrainingExample $example, array $aiResult): void
    {
        $usage = $this->normalizeUsage($aiResult['usage'] ?? null);

        $example->update([
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'provider' => $aiResult['provider'] ?? config('ai.provider'),
            'model' => $aiResult['model'] ?? config('ai.model'),
            'latency_ms' => $aiResult['latency_ms'] ?? null,
            'model_called' => $aiResult['model_called'] ?? true,
            'guardrail_blocked' => $aiResult['guardrail_blocked'] ?? false,
        ]);
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function zeroUsage(): array
    {
        return [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
    }
}
