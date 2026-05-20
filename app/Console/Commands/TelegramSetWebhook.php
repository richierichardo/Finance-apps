<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--url=}';

    protected $description = 'Register Telegram bot webhook URL';

    public function handle(TelegramBotService $bot): int
    {
        $url = $this->option('url');
        $result = $bot->setWebhook($url ?: null);

        if ($result['ok']) {
            $this->info('Webhook registered successfully.');
            $this->line(json_encode($result['data'] ?? [], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->error($result['error'] ?? 'Failed to set webhook.');

        return self::FAILURE;
    }
}
