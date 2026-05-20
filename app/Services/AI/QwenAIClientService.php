<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QwenAIClientService
{
    public function __construct(
        protected AITokenUsageService $tokenUsageService,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $options = []): array
    {
        $start = microtime(true);
        $apiKey = config('ai.api_key');
        $model = $options['model'] ?? config('ai.model');
        $provider = config('ai.provider', 'qwen');

        if (empty($apiKey)) {
            return $this->failure('AI service is not configured.', $start, $model, $provider, 'api_error');
        }

        $baseUrl = config('ai.base_url');
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? config('ai.temperature'),
            'max_tokens' => $options['max_tokens'] ?? config('ai.max_tokens'),
        ];

        if (! empty($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        try {
            $timeout = (int) ($options['timeout'] ?? config('ai.timeout', 30));

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", $payload);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if (! $response->successful()) {
                Log::warning('Qwen API error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return $this->failure(
                    'AI service is temporarily unavailable.',
                    $start,
                    $model,
                    $provider,
                    'api_error',
                    $latencyMs
                );
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            $usage = $this->tokenUsageService->normalizeUsage($data['usage'] ?? null);

            return [
                'ok' => true,
                'content' => is_string($content) ? trim($content) : null,
                'raw' => $data,
                'usage' => $usage,
                'model' => $model,
                'provider' => $provider,
                'error' => null,
                'error_type' => null,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            $errorType = $this->detectErrorType($e);

            Log::error('Qwen API exception', [
                'message' => $e->getMessage(),
                'error_type' => $errorType,
            ]);

            return $this->failure(
                'AI service is temporarily unavailable.',
                $start,
                $model,
                $provider,
                $errorType
            );
        }
    }

    private function detectErrorType(\Throwable $e): string
    {
        if ($e instanceof ConnectionException) {
            return 'timeout';
        }

        $message = mb_strtolower($e->getMessage());
        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'timeout';
        }

        return 'api_error';
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(
        string $message,
        float $start,
        string $model,
        string $provider,
        string $errorType,
        ?int $latencyMs = null,
    ): array {
        return [
            'ok' => false,
            'content' => null,
            'raw' => null,
            'usage' => $this->tokenUsageService->normalizeUsage(null),
            'model' => $model,
            'provider' => $provider,
            'error' => $message,
            'error_type' => $errorType,
            'latency_ms' => $latencyMs ?? (int) round((microtime(true) - $start) * 1000),
        ];
    }
}
