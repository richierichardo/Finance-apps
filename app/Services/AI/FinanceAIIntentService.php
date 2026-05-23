<?php

namespace App\Services\AI;

use App\Services\AI\Prompts\FinanceAISystemPrompt;

class FinanceAIIntentService
{
    private const COMPLETE_THRESHOLD = 0.85;

    private const PARTIAL_THRESHOLD = 0.65;

    public function __construct(
        protected QwenAIClientService $qwenClient,
        protected FinanceNLPNormalizerService $normalizer,
        protected FinanceAICommandParserService $commandParser,
        protected FinanceAIClarificationService $clarificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function parse(int $userId, string $userMessage, array $context, array $options = []): array
    {
        $commandResult = $this->commandParser->parse($userId, $userMessage, $context);

        return $this->parseWithFallback($userId, $userMessage, $this->normalizer->normalize($userMessage), $commandResult, $context, $options);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $partialRulesResult
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function parseWithFallback(
        int $userId,
        string $originalMessage,
        array $normalized,
        ?array $partialRulesResult,
        array $context,
        array $options = [],
    ): array {
        $commandResult = $partialRulesResult ?? $this->commandParser->parse($userId, $originalMessage, $context);

        if ($this->shouldUseCommandResult($commandResult)) {
            return $this->toIntentShape($commandResult);
        }

        if ($options['skip_qwen'] ?? false) {
            return $this->finalizePartialOrUnknown($commandResult);
        }

        $text = $normalized['normalized_text'] ?? '';
        $partialJson = $partialRulesResult !== null
            ? json_encode($partialRulesResult, JSON_UNESCAPED_UNICODE)
            : 'null';
        $missingFields = $commandResult['missing_fields'] ?? [];
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

        $messages = [
            ['role' => 'system', 'content' => FinanceAISystemPrompt::repairParse()],
            ['role' => 'user', 'content' => implode("\n", [
                "Context:\n{$contextJson}",
                "Original message:\n{$originalMessage}",
                "Normalized:\n{$text}",
                "Partial rule parse:\n{$partialJson}",
                'Missing fields: '.json_encode($missingFields, JSON_UNESCAPED_UNICODE),
            ])],
        ];

        $qwenOptions = array_merge([
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => (int) config('ai.intent_max_tokens', 400),
            'timeout' => (int) config('ai.intent_timeout_seconds', 8),
        ], $options['qwen_options'] ?? []);

        $response = $this->qwenClient->chat($messages, $qwenOptions);

        if (($response['ok'] ?? false) && ! empty($response['content'])) {
            $parsed = $this->decodeIntentJson($response['content']);
            if ($parsed && ($parsed['confidence'] ?? 0) >= self::PARTIAL_THRESHOLD) {
                $intent = $this->mergeRepairResult($commandResult, $parsed);
                $intent['model_called'] = true;
                $intent['source'] = 'qwen_repair';

                return $intent;
            }
        }

        return $this->finalizePartialOrUnknown($commandResult);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function ruleBasedParse(int $userId, string $text, array $context, ?array $normalized = null): array
    {
        return $this->toIntentShape($this->commandParser->parse($userId, $text, $context));
    }

    /**
     * @param  array<string, mixed>  $commandResult
     */
    private function shouldUseCommandResult(array $commandResult): bool
    {
        return ($commandResult['matched'] ?? false)
            && ($commandResult['confidence'] ?? 0) >= self::COMPLETE_THRESHOLD
            && empty($commandResult['missing_fields']);
    }

    /**
     * @param  array<string, mixed>  $commandResult
     * @return array<string, mixed>
     */
    private function finalizePartialOrUnknown(array $commandResult): array
    {
        if ($commandResult['matched'] ?? false) {
            $intent = $this->toIntentShape($commandResult);
            $actionType = $intent['action_type'] ?? null;
            if (! empty($intent['missing_fields']) && $actionType) {
                $intent['clarifying_question'] = $this->clarificationService->buildFieldSpecificClarification(
                    $actionType,
                    $intent['missing_fields'],
                    $intent['entities'] ?? []
                ) ?? $intent['clarifying_question'];
            }

            return $intent;
        }

        return $this->toIntentShape([
            'matched' => false,
            'intent' => 'unknown',
            'confidence' => 0.3,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => 'Aku belum bisa memahami perintah itu. Coba tulis seperti: catat pengeluaran 25 ribu dari GOPAY buat kopi.',
            'model_called' => false,
            'source' => 'rules',
        ]);
    }

    /**
     * @param  array<string, mixed>  $commandResult
     * @param  array<string, mixed>  $aiParsed
     * @return array<string, mixed>
     */
    private function mergeRepairResult(array $commandResult, array $aiParsed): array
    {
        $rulesEntities = $commandResult['entities'] ?? [];
        $aiEntities = $aiParsed['entities'] ?? [];

        if (($aiParsed['action_type'] ?? '') === 'create_wallet' || ($commandResult['action_type'] ?? '') === 'create_wallet') {
            $merged = $this->clarificationService->normalizeWalletEntities(array_merge($rulesEntities, array_filter($aiEntities, fn ($v) => $v !== null && $v !== '')));
            $aiEntities = $merged;
        } else {
            $aiEntities = array_merge($rulesEntities, array_filter($aiEntities, fn ($v) => $v !== null && $v !== ''));
        }

        $missing = [];
        $actionType = $aiParsed['action_type'] ?? $commandResult['action_type'] ?? null;

        if ($actionType === 'create_wallet') {
            if (empty($aiEntities['wallet_name'])) {
                $missing[] = 'wallet_name';
            }
            if (empty($aiEntities['wallet_type'])) {
                $missing[] = 'wallet_type';
            }
        } else {
            $missing = $aiParsed['missing_fields'] ?? $commandResult['missing_fields'] ?? [];
        }

        $clarifying = empty($missing)
            ? null
            : $this->clarificationService->buildFieldSpecificClarification($actionType ?? '', $missing, $aiEntities);

        return [
            'intent' => $aiParsed['intent'] ?? $commandResult['intent'] ?? 'unknown',
            'confidence' => $aiParsed['confidence'] ?? $commandResult['confidence'] ?? 0.0,
            'language' => 'id',
            'requires_action' => $aiParsed['requires_action'] ?? $commandResult['requires_action'] ?? false,
            'action_type' => $actionType,
            'entities' => $aiEntities,
            'missing_fields' => $missing,
            'clarifying_question' => $clarifying,
            'safety_flags' => $aiParsed['safety_flags'] ?? [],
            'matched' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $commandResult
     * @return array<string, mixed>
     */
    private function toIntentShape(array $commandResult): array
    {
        return [
            'intent' => $commandResult['intent'] ?? 'unknown',
            'confidence' => $commandResult['confidence'] ?? 0.0,
            'language' => 'id',
            'requires_action' => $commandResult['requires_action'] ?? false,
            'action_type' => $commandResult['action_type'] ?? null,
            'entities' => $commandResult['entities'] ?? [],
            'missing_fields' => $commandResult['missing_fields'] ?? [],
            'clarifying_question' => $commandResult['clarifying_question'] ?? null,
            'safety_flags' => $commandResult['safety_flags'] ?? [],
            'model_called' => $commandResult['model_called'] ?? false,
            'source' => $commandResult['source'] ?? 'rules',
            'matched' => $commandResult['matched'] ?? false,
            'debug' => $commandResult['debug'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalizeIntentFromLlm(array $parsed): array
    {
        return array_merge([
            'intent' => 'unknown',
            'confidence' => 0.0,
            'language' => 'id',
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'safety_flags' => [],
        ], $parsed);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeIntentJson(string $content): ?array
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }
}
