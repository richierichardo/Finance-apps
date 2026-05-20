<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    private function apiUrl(string $method): string
    {
        $token = config('telegram.bot_token');

        return "https://api.telegram.org/bot{$token}/{$method}";
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, data: array|null, error: string|null}
     */
    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        if (empty(config('telegram.bot_token'))) {
            return ['ok' => false, 'data' => null, 'error' => 'Telegram bot not configured'];
        }

        try {
            $response = Http::timeout(15)->post($this->apiUrl('sendMessage'), array_merge([
                'chat_id' => $chatId,
                'text' => $this->formatMessage($text),
                'parse_mode' => $options['parse_mode'] ?? 'Markdown',
            ], $options));

            if (! $response->successful()) {
                Log::warning('Telegram sendMessage failed', ['status' => $response->status()]);

                return ['ok' => false, 'data' => null, 'error' => 'Failed to send message'];
            }

            return ['ok' => true, 'data' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'data' => null, 'error' => 'Failed to send message'];
        }
    }

    /**
     * @return array{ok: bool, data: array|null, error: string|null}
     */
    public function setWebhook(?string $url = null): array
    {
        $webhookUrl = $url ?? config('telegram.webhook_url');
        if (! $webhookUrl) {
            $secret = config('telegram.webhook_secret');
            $webhookUrl = rtrim(config('app.url'), '/')."/telegram/webhook/{$secret}";
        }

        try {
            $response = Http::timeout(15)->post($this->apiUrl('setWebhook'), [
                'url' => $webhookUrl,
                'secret_token' => config('telegram.webhook_secret'),
            ]);

            return [
                'ok' => $response->successful(),
                'data' => $response->json(),
                'error' => $response->successful() ? null : 'setWebhook failed',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, data: array|null, error: string|null}
     */
    public function deleteWebhook(): array
    {
        try {
            $response = Http::timeout(15)->post($this->apiUrl('deleteWebhook'));

            return [
                'ok' => $response->successful(),
                'data' => $response->json(),
                'error' => $response->successful() ? null : 'deleteWebhook failed',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, data: array|null, error: string|null}
     */
    public function getMe(): array
    {
        try {
            $response = Http::timeout(15)->get($this->apiUrl('getMe'));

            return [
                'ok' => $response->successful(),
                'data' => $response->json(),
                'error' => $response->successful() ? null : 'getMe failed',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, data: array|null, error: string|null}
     */
    public function sendChatAction(string $chatId, string $action = 'typing'): array
    {
        if (empty(config('telegram.bot_token'))) {
            return ['ok' => false, 'data' => null, 'error' => 'Telegram bot not configured'];
        }

        try {
            $response = Http::timeout(10)->post($this->apiUrl('sendChatAction'), [
                'chat_id' => $chatId,
                'action' => $action,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram sendChatAction failed', ['status' => $response->status()]);

                return ['ok' => false, 'data' => null, 'error' => 'Failed to send chat action'];
            }

            return ['ok' => true, 'data' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Telegram sendChatAction exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'data' => null, 'error' => 'Failed to send chat action'];
        }
    }

    public function formatMessage(string $text): string
    {
        return mb_substr($text, 0, 4096);
    }
}
