<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramFinanceCommandRouter;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Queue cleanup (run after deploy or stuck replies):
 * php artisan queue:restart
 * php artisan queue:clear database --queue=telegram
 * php artisan queue:flush
 */
class ProcessTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

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
        TelegramFinanceCommandRouter $router,
    ): void {
        $jobStarted = microtime(true);
        $chatId = (string) ($this->payload['telegram_chat_id'] ?? '');
        $telegramUserId = (string) ($this->payload['telegram_user_id'] ?? '');
        $text = trim((string) ($this->payload['text'] ?? ''));
        $updateId = $this->payload['update_id'] ?? null;

        if ($updateId !== null) {
            $processedKey = 'telegram_update_processed:'.$updateId;
            $sentKey = 'telegram_response_sent:'.$updateId;

            if (Cache::has($processedKey) || Cache::has($sentKey)) {
                Log::info('telegram.job.duplicate_skipped', [
                    'update_id' => $updateId,
                    'telegram_user_id' => $telegramUserId,
                ]);

                return;
            }

            $processingKey = 'telegram_update_processing:'.$updateId;
            if (! Cache::add($processingKey, true, now()->addMinutes(10))) {
                Log::info('telegram.job.processing_skipped', [
                    'update_id' => $updateId,
                    'telegram_user_id' => $telegramUserId,
                ]);

                return;
            }
        }

        $userId = $linkService->findLinkedUserId($telegramUserId);
        if (! $userId) {
            if ($updateId !== null) {
                Cache::forget('telegram_update_processing:'.$updateId);
            }
            $telegramBot->sendMessage(
                $chatId,
                'Akun belum terhubung. Buka Flowlet → Profile → Generate Telegram link code, lalu kirim /link KODE.'
            );

            return;
        }

        $linkService->touchLastSeen($telegramUserId);
        $telegramBot->sendChatAction($chatId, 'typing');

        $responseSent = false;

        try {
            $routerStarted = microtime(true);
            $result = $router->handle($userId, $text, [
                'telegram_chat_id' => $chatId,
            ]);
            $routerMs = (int) round((microtime(true) - $routerStarted) * 1000);

            if ((microtime(true) - $jobStarted) > 3) {
                $telegramBot->sendChatAction($chatId, 'typing');
            }

            $sendStarted = microtime(true);
            $telegramBot->sendMessage($chatId, $result['message'] ?? 'Maaf, tidak ada respons.');
            $sendMs = (int) round((microtime(true) - $sendStarted) * 1000);
            $responseSent = true;

            if ($updateId !== null) {
                Cache::put('telegram_response_sent:'.$updateId, true, now()->addHours(24));
                Cache::put('telegram_update_processed:'.$updateId, true, now()->addHours(24));
                Cache::forget('telegram_update_processing:'.$updateId);
            }

            Log::info('telegram.job.completed', [
                'update_id' => $updateId,
                'telegram_user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'path' => 'router',
                'router_ms' => $routerMs,
                'telegram_send_ms' => $sendMs,
                'job_total_ms' => (int) round((microtime(true) - $jobStarted) * 1000),
                'intent' => $result['structured']['intent'] ?? ($result['type'] ?? null),
            ]);
        } catch (\Throwable $e) {
            if ($updateId !== null) {
                Cache::forget('telegram_update_processing:'.$updateId);
            }

            Log::error('telegram.job.failed', [
                'update_id' => $updateId,
                'telegram_user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
                'response_sent' => $responseSent,
                'job_total_ms' => (int) round((microtime(true) - $jobStarted) * 1000),
            ]);

            if (! $responseSent) {
                $telegramBot->sendMessage(
                    $chatId,
                    'Maaf, terjadi kesalahan. Coba lagi sebentar ya.'
                );
                throw $e;
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $chatId = (string) ($this->payload['telegram_chat_id'] ?? '');
        $updateId = $this->payload['update_id'] ?? null;

        if ($chatId === '') {
            return;
        }

        if ($updateId !== null && (Cache::has('telegram_response_sent:'.$updateId) || Cache::has('telegram_update_processed:'.$updateId))) {
            Log::info('telegram.job.failed_after_send', [
                'update_id' => $updateId,
                'message' => $exception?->getMessage(),
            ]);

            return;
        }

        Log::error('telegram.job.exhausted_retries', [
            'update_id' => $updateId,
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
