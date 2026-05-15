<?php

namespace App\Services\LLM;

use App\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAIProvider implements LLMProviderInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(string $prompt, array $options = []): string
    {
        $apiKey = $this->apiKey ?? config('services.openai.api_key') ?? env('OPENAI_API_KEY');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $model = $options['model']
            ?? config('services.openai.model')
            ?? env('INSIGHT_LLM_MODEL', 'gpt-4o-mini');

        $maxTokens = $options['max_tokens'] ?? 800;
        $temperature = $options['temperature'] ?? 0.3;
        $systemPrompt = $options['system_prompt'] ?? 'You are a helpful assistant.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('Failed to call OpenAI API.');
            }

            $data = $response->json();

            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable $e) {
            Log::error('OpenAIProvider generate() failed', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('LLM generation failed.', 0, $e);
        }
    }
}
