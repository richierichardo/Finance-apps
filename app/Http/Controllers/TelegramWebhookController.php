<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramMessageJob;
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

        if (str_starts_with($text, '/start')) {
            $this->telegramBot->sendMessage(
                $chatId,
                "Hi! Saya Flowlet Assistant.\n\n"
                ."Hubungkan akun dulu dari dashboard web, lalu kirim:\n/link KODE\n\n"
                ."Ketik /help untuk bantuan."
            );

            return response('ok', 200);
        }

        if (str_starts_with($text, '/help')) {
            $this->telegramBot->sendMessage($chatId, $this->helpText());

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

    private function helpText(): string
    {
        return "/summary - ringkasan bulan ini\n"
            ."/forecast - proyeksi bulan ini\n"
            ."/wallets - saldo wallet\n"
            ."/cancel - batalkan aksi pending\n"
            ."/link KODE - hubungkan akun\n\n"
            ."Contoh natural language:\n"
            ."• saldo gue berapa?\n"
            ."• catat pengeluaran 25000 dari GOPAY buat kopi\n"
            ."• transfer 50000 dari GOPAY ke SHOPEEPAY";
    }
}
