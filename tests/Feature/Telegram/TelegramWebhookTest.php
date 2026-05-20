<?php

use App\Jobs\ProcessTelegramMessageJob;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['telegram.webhook_secret' => 'test-secret']);
    $this->user = User::factory()->create(['username' => 'tg_'.Str::random(8)]);
});

test('telegram webhook rejects invalid secret', function () {
    $this->postJson('/telegram/webhook/wrong-secret', [])
        ->assertStatus(403);
});

test('telegram webhook tells unlinked user to link account without queueing', function () {
    Queue::fake();

    $this->mock(TelegramBotService::class, function ($mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with('12345', Mockery::type('string'))
            ->andReturn(['ok' => true]);
    });

    $this->postJson('/telegram/webhook/test-secret', [
        'update_id' => 1001,
        'message' => [
            'text' => 'saldo berapa?',
            'chat' => ['id' => 12345],
            'from' => ['id' => '999001'],
        ],
    ])->assertOk();

    Queue::assertNothingPushed();
});

test('telegram webhook dispatches job for linked user', function () {
    Queue::fake();

    TelegramAccount::create([
        'user_id' => $this->user->id,
        'telegram_user_id' => '999002',
        'telegram_chat_id' => '12346',
        'is_active' => true,
        'linked_at' => now(),
    ]);

    $this->postJson('/telegram/webhook/test-secret', [
        'update_id' => 1002,
        'message' => [
            'text' => '/wallets',
            'chat' => ['id' => 12346],
            'from' => ['id' => '999002'],
        ],
    ])->assertOk();

    Queue::assertPushed(ProcessTelegramMessageJob::class, function ($job) {
        return $job->queue === 'telegram'
            && $job->payload['text'] === '/wallets'
            && $job->payload['telegram_user_id'] === '999002';
    });
});

test('telegram webhook handles link synchronously', function () {
    Queue::fake();

    $this->mock(TelegramBotService::class, function ($mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andReturn(['ok' => true]);
    });

    $this->postJson('/telegram/webhook/test-secret', [
        'update_id' => 1003,
        'message' => [
            'text' => '/link INVALID',
            'chat' => ['id' => 12347],
            'from' => ['id' => '999003'],
        ],
    ])->assertOk();

    Queue::assertNothingPushed();
});
