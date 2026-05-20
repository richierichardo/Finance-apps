<?php

namespace App\Jobs;

use App\Services\AI\FinanceAIOrchestratorService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramFastReplyService;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(
        TelegramLinkService $linkService,
        TelegramBotService $telegramBot,
        TelegramFastReplyService $fastReply,
        FinanceAIOrchestratorService $orchestrator,
    ): void {
        $jobStarted = microtime(true);
        $chatId = (string) ($this->payload['telegram_chat_id'] ?? '');
        $telegramUserId = (string) ($this->payload['telegram_user_id'] ?? '');
        $text = trim((string) ($this->payload['text'] ?? ''));
        $updateId = $this->payload['update_id'] ?? null;

        if ($updateId !== null) {
            $cacheKey = 'telegram_update_processed:'.$updateId;
            if (! Cache::add($cacheKey, true, now()->addHour())) {
                Log::info('telegram.job.duplicate_skipped', [
                    'update_id' => $updateId,
                    'telegram_user_id' => $telegramUserId,
                ]);

                return;
            }
        }

        $userId = $linkService->findLinkedUserId($telegramUserId);
        if (! $userId) {
            $telegramBot->sendMessage(
                $chatId,
                'Akun belum terhubung. Buka Flowlet → Profile → Generate Telegram link code, lalu kirim /link KODE.'
            );

            return;
        }

        $linkService->touchLastSeen($telegramUserId);
        $telegramBot->sendChatAction($chatId, 'typing');

        try {
            $fastStarted = microtime(true);
            $fastMessage = $fastReply->tryFastReply($userId, $text, $chatId);
            $fastPathMs = (int) round((microtime(true) - $fastStarted) * 1000);

            if ($fastMessage !== null) {
                $sendStarted = microtime(true);
                $telegramBot->sendMessage($chatId, $fastMessage);
                $sendMs = (int) round((microtime(true) - $sendStarted) * 1000);

                Log::info('telegram.job.completed', [
                    'update_id' => $updateId,
                    'telegram_user_id' => $telegramUserId,
                    'chat_id' => $chatId,
                    'path' => 'fast',
                    'fast_path_ms' => $fastPathMs,
                    'telegram_send_ms' => $sendMs,
                    'job_total_ms' => (int) round((microtime(true) - $jobStarted) * 1000),
                ]);

                return;
            }

            $aiStarted = microtime(true);
            $result = $orchestrator->handleUserMessage($userId, $text, 'telegram', [
                'telegram_chat_id' => $chatId,
                'prefer_rules_first' => true,
            ]);
            $aiLatencyMs = (int) round((microtime(true) - $aiStarted) * 1000);

            if ((microtime(true) - $jobStarted) > 3) {
                $telegramBot->sendChatAction($chatId, 'typing');
            }

            $sendStarted = microtime(true);
            $telegramBot->sendMessage($chatId, $result['message'] ?? 'Maaf, tidak ada respons.');
            $sendMs = (int) round((microtime(true) - $sendStarted) * 1000);

            Log::info('telegram.job.completed', [
                'update_id' => $updateId,
                'telegram_user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'path' => 'orchestrator',
                'fast_path_ms' => $fastPathMs,
                'ai_latency_ms' => $aiLatencyMs,
                'telegram_send_ms' => $sendMs,
                'job_total_ms' => (int) round((microtime(true) - $jobStarted) * 1000),
                'intent' => $result['structured']['intent'] ?? ($result['type'] ?? null),
            ]);
        } catch (\Throwable $e) {
            Log::error('telegram.job.failed', [
                'update_id' => $updateId,
                'telegram_user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
                'job_total_ms' => (int) round((microtime(true) - $jobStarted) * 1000),
            ]);

            $telegramBot->sendMessage(
                $chatId,
                'Maaf, terjadi kesalahan. Coba lagi sebentar ya.'
            );

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $chatId = (string) ($this->payload['telegram_chat_id'] ?? '');
        if ($chatId === '') {
            return;
        }

        Log::error('telegram.job.exhausted_retries', [
            'update_id' => $this->payload['update_id'] ?? null,
            'telegram_user_id' => $this->payload['telegram_user_id'] ?? null,
            'chat_id' => $chatId,
            'message' => $exception?->getMessage(),
        ]);

        try {
            app(TelegramBotService::class)->sendMessage(
                $chatId,
                'Maaf, AI sedang bermasalah. Coba lagi sebentar ya.'
            );
        } catch (\Throwable) {
            // Avoid secondary failure loops
        }
    }
}
