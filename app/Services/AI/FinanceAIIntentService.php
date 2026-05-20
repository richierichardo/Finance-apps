<?php

namespace App\Services\AI;

use App\Services\AI\Prompts\FinanceAISystemPrompt;

class FinanceAIIntentService
{
    private const COMPLETE_THRESHOLD = 0.85;

    private const PARTIAL_THRESHOLD = 0.7;

    public function __construct(
        protected QwenAIClientService $qwenClient,
        protected FinanceNLPNormalizerService $normalizer,
        protected FinanceAICommandParserService $commandParser,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function parse(int $userId, string $userMessage, array $context, array $options = []): array
    {
        $commandResult = $this->commandParser->parse($userId, $userMessage, $context);

        if ($this->shouldUseCommandResult($commandResult)) {
            return $this->toIntentShape($commandResult);
        }

        if ($this->shouldReturnClarification($commandResult)) {
            return $this->toIntentShape($commandResult);
        }

        if ($options['skip_qwen'] ?? false) {
            return $this->toIntentShape($commandResult['matched'] ? $commandResult : [
                'matched' => false,
                'intent' => 'unknown',
                'confidence' => 0.3,
                'requires_action' => false,
                'action_type' => null,
                'entities' => [],
                'missing_fields' => [],
                'clarifying_question' => 'Aku belum bisa memahami perintah itu. Coba tulis seperti: catat pengeluaran 25 ribu dari GOPAY buat kopi.',
            ]);
        }

        $normalized = $this->normalizer->normalize($userMessage);
        $text = $normalized['normalized_text'];

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $messages = [
            ['role' => 'system', 'content' => FinanceAISystemPrompt::intentExtraction()],
            ['role' => 'user', 'content' => "Context:\n{$contextJson}\n\nUser message:\n{$userMessage}\n\nNormalized:\n{$text}"],
        ];

        $qwenOptions = array_merge([
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => (int) config('ai.intent_max_tokens', 400),
            'timeout' => (int) config('ai.intent_timeout_seconds', 8),
        ], $options['qwen_options'] ?? []);

        $response = $this->qwenClient->chat($messages, $qwenOptions);

        if ($response['ok'] && $response['content']) {
            $parsed = $this->decodeIntentJson($response['content']);
            if ($parsed && ($parsed['confidence'] ?? 0) >= self::PARTIAL_THRESHOLD) {
                $intent = $this->normalizeIntentFromLlm($parsed);
                $intent['model_called'] = true;
                $intent['source'] = 'qwen';

                return $intent;
            }
        }

        if ($commandResult['matched'] ?? false) {
            return $this->toIntentShape($commandResult);
        }

        return $this->toIntentShape([
            'matched' => false,
            'intent' => 'unknown',
            'confidence' => 0.3,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => $response['error_type'] === 'timeout'
                ? 'Aku belum bisa memahami perintah itu. Coba tulis seperti: catat pengeluaran 25 ribu dari GOPAY buat kopi.'
                : 'Aku belum bisa memahami perintah itu. Coba tulis seperti: transfer 20 ribu dari GOPAY ke SHOPEEPAY.',
            'model_called' => true,
            'source' => 'qwen_fallback',
        ]);
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
     */
    private function shouldReturnClarification(array $commandResult): bool
    {
        return ($commandResult['matched'] ?? false)
            && ($commandResult['confidence'] ?? 0) >= self::PARTIAL_THRESHOLD
            && ! empty($commandResult['missing_fields']);
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
