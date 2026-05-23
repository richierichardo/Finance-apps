<?php

use App\Jobs\ProcessTelegramMessageJob;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramFinanceCommandRouter;
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

test('job sends router wallet reply', function () {
    $this->mock(TelegramFinanceCommandRouter::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->andReturn([
                'ok' => true,
                'type' => 'answer',
                'message' => 'Total saldo: Rp 50.000.',
                'structured' => ['intent' => 'ask_wallet_balance'],
                'draft_id' => null,
            ]);
    });

    $this->mock(TelegramBotService::class, function ($mock) {
        $mock->shouldReceive('sendChatAction')->once()->andReturn(['ok' => true]);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with('555001', Mockery::on(fn ($text) => str_contains($text, '50.000')))
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
        app(TelegramFinanceCommandRouter::class),
    );

    expect(Cache::has('telegram_update_processed:9001'))->toBeTrue()
        ->and(Cache::has('telegram_response_sent:9001'))->toBeTrue();
});

test('job skips duplicate update id', function () {
    Cache::put('telegram_update_processed:9002', true, now()->addHours(24));

    $this->mock(TelegramBotService::class, function ($mock) {
        $mock->shouldNotReceive('sendMessage');
        $mock->shouldNotReceive('sendChatAction');
    });

    $this->mock(TelegramFinanceCommandRouter::class, function ($mock) {
        $mock->shouldNotReceive('handle');
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
        app(TelegramFinanceCommandRouter::class),
    );
});

test('job skips when response already sent', function () {
    Cache::put('telegram_response_sent:9003', true, now()->addHours(24));

    $this->mock(TelegramFinanceCommandRouter::class, function ($mock) {
        $mock->shouldNotReceive('handle');
    });

    $job = new ProcessTelegramMessageJob([
        'update_id' => 9003,
        'telegram_user_id' => '888001',
        'telegram_chat_id' => '555001',
        'text' => '/wallets',
    ]);

    $job->handle(
        app(\App\Services\Telegram\TelegramLinkService::class),
        app(TelegramBotService::class),
        app(TelegramFinanceCommandRouter::class),
    );
});
