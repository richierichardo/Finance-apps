<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramDeleteWebhook extends Command
{
    protected $signature = 'telegram:delete-webhook';

    protected $description = 'Remove Telegram bot webhook';

    public function handle(TelegramBotService $bot): int
    {
        $result = $bot->deleteWebhook();

        if ($result['ok']) {
            $this->info('Webhook deleted successfully.');

            return self::SUCCESS;
        }

        $this->error($result['error'] ?? 'Failed to delete webhook.');

        return self::FAILURE;
    }
}
