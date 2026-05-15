<?php

namespace App\Services;

use App\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Log;

class LLMInsightService
{
    public function __construct(
        protected InsightDataService $insightDataService,
        protected RuleBasedInsightService $ruleBasedInsightService,
        protected InsightPromptBuilder $promptBuilder,
        protected LLMProviderInterface $llmProvider,
    ) {}

    public function generateNarrativeInsight(int $userId, string $periodKey): ?string
    {
        if (! $this->isLLMEnabled()) {
            return null;
        }

        if (! $this->canCallLLM($userId)) {
            return null;
        }

        try {
            $aggregated = $this->insightDataService->aggregateForPeriod($userId, $periodKey);
            $ruleInsights = $this->ruleBasedInsightService->generateInsights($userId, $periodKey);

            $prompt = $this->promptBuilder->buildMonthlySummaryPrompt($aggregated, $ruleInsights);

            if (trim($prompt) === '') {
                return null;
            }

            $response = $this->llmProvider->generate($prompt, [
                'model' => config('services.openai.model') ?? env('INSIGHT_LLM_MODEL', 'gpt-4o-mini'),
                'max_tokens' => 800,
                'temperature' => 0.3,
                'system_prompt' => 'You are a helpful personal finance assistant.',
            ]);

            return $response !== '' ? $response : null;
        } catch (\Throwable $e) {
            Log::error('LLMInsightService generateNarrativeInsight failed', [
                'user_id' => $userId,
                'period_key' => $periodKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isLLMEnabled(): bool
    {
        if (! config('insights.llm_enabled', true)) {
            return false;
        }

        $apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');

        return ! empty($apiKey);
    }

    private function canCallLLM(int $userId): bool
    {
        $key = "ai_insight.llm_calls.{$userId}";
        $limit = 5;

        $count = cache()->increment($key);
        if ($count === 1) {
            cache()->put($key, $count, now()->addMinute());
        }

        return $count <= $limit;
    }
}
