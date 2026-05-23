<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramMessageJob;
use App\Services\Telegram\TelegramBotMessages;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramBotService $telegramBot,
        protected TelegramLinkService $linkService,
    ) {}

    public function handle(Request $request, string $secret): Response
    {
        $webhookStarted = microtime(true);

        if ($secret !== config('telegram.webhook_secret')) {
            return response('', 403);
        }

        $update = $request->all();
        $message = $update['message'] ?? null;

        if (! $message || empty($message['text'])) {
            return response('ok', 200);
        }

        $text = trim($message['text']);
        $chatId = (string) ($message['chat']['id'] ?? '');
        $telegramUserId = (string) ($message['from']['id'] ?? '');
        $profile = [
            'username' => $message['from']['username'] ?? null,
            'first_name' => $message['from']['first_name'] ?? null,
            'last_name' => $message['from']['last_name'] ?? null,
        ];

        $allowed = config('telegram.allowed_user_ids', []);
        if (! empty($allowed) && ! in_array($telegramUserId, $allowed, true)) {
            $this->telegramBot->sendMessage($chatId, 'Bot ini sedang dalam mode testing privat.');

            return response('ok', 200);
        }

        if ($reply = $this->syncSlashCommandReply($text)) {
            $this->telegramBot->sendMessage($chatId, $reply);

            return response('ok', 200);
        }

        if (preg_match('/^\/link\s+(\S+)/i', $text, $m)) {
            $result = $this->linkService->linkAccount($m[1], $telegramUserId, $chatId, $profile);
            $this->telegramBot->sendMessage($chatId, $result['message']);

            return response('ok', 200);
        }

        $userId = $this->linkService->findLinkedUserId($telegramUserId);

        if (! $userId) {
            $this->telegramBot->sendMessage(
                $chatId,
                'Akun belum terhubung. Buka Flowlet → Profile → Generate Telegram link code, lalu kirim /link KODE.'
            );

            return response('ok', 200);
        }

        ProcessTelegramMessageJob::dispatch([
            'update_id' => $update['update_id'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'telegram_user_id' => $telegramUserId,
            'telegram_chat_id' => $chatId,
            'username' => $profile['username'],
            'first_name' => $profile['first_name'],
            'last_name' => $profile['last_name'],
            'text' => $text,
            'webhook_received_at' => now()->toIso8601String(),
        ])->onQueue('telegram');

        Log::info('telegram.webhook.dispatched', [
            'update_id' => $update['update_id'] ?? null,
            'telegram_user_id' => $telegramUserId,
            'chat_id' => $chatId,
            'dispatch_ms' => (int) round((microtime(true) - $webhookStarted) * 1000),
        ]);

        return response('ok', 200);
    }

    /**
     * Commands answered immediately in webhook (no queue): start, help, tutorials, bare /link.
     */
    private function syncSlashCommandReply(string $text): ?string
    {
        $commandKey = TelegramBotMessages::parseSlashCommand($text);
        if ($commandKey === null) {
            return null;
        }

        if ($commandKey === 'start') {
            return TelegramBotMessages::welcome();
        }

        if ($commandKey === 'help') {
            return TelegramBotMessages::help();
        }

        if ($commandKey === 'link' && ! TelegramBotMessages::hasLinkCode($text)) {
            return TelegramBotMessages::linkTutorial();
        }

        if (TelegramBotMessages::isTutorialCommand($commandKey)) {
            return TelegramBotMessages::tutorial($commandKey);
        }

        return null;
    }
}
