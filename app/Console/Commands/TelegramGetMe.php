<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramGetMe extends Command
{
    protected $signature = 'telegram:get-me';

    protected $description = 'Get Telegram bot info (no token printed)';

    public function handle(TelegramBotService $bot): int
    {
        $result = $bot->getMe();

        if ($result['ok']) {
            $username = $result['data']['result']['username'] ?? 'unknown';
            $this->info("Bot username: @{$username}");

            return self::SUCCESS;
        }

        $this->error($result['error'] ?? 'Failed to get bot info.');

        return self::FAILURE;
    }
}
