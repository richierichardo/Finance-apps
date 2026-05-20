<?php

use App\Jobs\ProcessTelegramMessageJob;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramFastReplyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create(['username' => 'job_'.Str::random(8)]);

    TelegramAccount::create([
        'user_id' => $this->user->id,
        'telegram_user_id' => '888001',
        'telegram_chat_id' => '555001',
        'is_active' => true,
        'linked_at' => now(),
    ]);

    Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 50000,
        'balance' => 50000,
        'is_active' => true,
    ]);
});

test('job sends fast wallet reply without orchestrator', function () {
    $this->mock(TelegramBotService::class, function ($mock) {
        $mock->shouldReceive('sendChatAction')->once()->andReturn(['ok' => true]);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with('555001', Mockery::on(fn ($text) => str_contains($text, 'GOPAY')))
            ->andReturn(['ok' => true]);
    });

    $job = new ProcessTelegramMessageJob([
        'update_id' => 9001,
        'message_id' => 1,
        'telegram_user_id' => '888001',
        'telegram_chat_id' => '555001',
        'text' => '/wallets',
    ]);

    $job->handle(
        app(\App\Services\Telegram\TelegramLinkService::class),
        app(TelegramBotService::class),
        app(TelegramFastReplyService::class),
        app(\App\Services\AI\FinanceAIOrchestratorService::class),
    );
});

test('job skips duplicate update id', function () {
    Cache::put('telegram_update_processed:9002', true, now()->addHour());

    $this->mock(TelegramBotService::class, function ($mock) {
        $mock->shouldNotReceive('sendMessage');
        $mock->shouldNotReceive('sendChatAction');
    });

    $job = new ProcessTelegramMessageJob([
        'update_id' => 9002,
        'telegram_user_id' => '888001',
        'telegram_chat_id' => '555001',
        'text' => '/wallets',
    ]);

    $job->handle(
        app(\App\Services\Telegram\TelegramLinkService::class),
        app(TelegramBotService::class),
        app(TelegramFastReplyService::class),
        app(\App\Services\AI\FinanceAIOrchestratorService::class),
    );
});
