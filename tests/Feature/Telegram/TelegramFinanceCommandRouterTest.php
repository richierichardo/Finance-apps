<?php

use App\Enums\WalletType;
use App\Models\AiActionDraft;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AI\FinanceAIOrchestratorService;
use App\Services\AI\QwenAIClientService;
use App\Services\Telegram\TelegramBotMessages;
use App\Services\Telegram\TelegramFinanceCommandRouter;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'tgrouter_'.Str::random(8)]);
    $this->chatId = '777001';
    $this->context = ['telegram_chat_id' => $this->chatId];
    $this->router = app(TelegramFinanceCommandRouter::class);

    Category::query()->firstOrCreate(
        ['slug' => 'belanja'],
        ['name' => 'Belanja', 'type' => 'expense']
    );
});

function createCollectingBudgetDraft(User $user, string $chatId, array $missing = ['category_name']): AiActionDraft
{
    return AiActionDraft::create([
        'user_id' => $user->id,
        'channel' => 'telegram',
        'telegram_chat_id' => $chatId,
        'action_type' => 'create_budget',
        'payload' => [
            'partial_entities' => ['amount' => 1000000, 'period' => 'monthly'],
            'missing_fields' => $missing,
            'expected_next_field' => $missing[0] ?? null,
        ],
        'status' => AiActionDraft::STATUS_COLLECTING,
        'expires_at' => now()->addMinutes(30),
    ]);
}

test('pending budget draft user sends wallets returns wallet list', function () {
    createCollectingBudgetDraft($this->user, $this->chatId);

    $result = $this->router->handle($this->user->id, '/wallets', $this->context);

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toContain('Total saldo')
        ->and($result['message'])->not->toContain('Kategori apa');
});

test('pending budget draft user sends saldo gw berapa returns balance', function () {
    createCollectingBudgetDraft($this->user, $this->chatId);

    $result = $this->router->handle($this->user->id, 'saldo gw berapa sekarang', $this->context);

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toContain('Total saldo')
        ->and($result['message'])->not->toContain('Kategori apa');
});

test('pending budget draft user sends belanja fills category slot', function () {
    $draft = createCollectingBudgetDraft($this->user, $this->chatId);

    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = $this->router->handle($this->user->id, 'belanja', $this->context);

    expect($result['type'])->toBeIn(['confirmation_required', 'clarification'])
        ->and($draft->fresh()->status)->toBe(AiActionDraft::STATUS_CANCELLED);
});

test('pending budget draft user sends transfer cancels old and starts transfer', function () {
    createCollectingBudgetDraft($this->user, $this->chatId);

    Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 100000,
        'balance' => 100000,
        'is_active' => true,
    ]);
    Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'SHOPEEPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 200000,
        'balance' => 200000,
        'is_active' => true,
    ]);

    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = $this->router->handle(
        $this->user->id,
        'transfer 20 ribu dari GOPAY ke SHOPEEPAY',
        $this->context
    );

    expect($result['message'])->toContain('batalkan')
        ->and($result['type'])->toBe('confirmation_required');
});

test('set budget kategori belanja 1 jt setiap bulan parses correctly', function () {
    $parser = app(\App\Services\AI\FinanceAICommandParserService::class);
    $context = ['user_id' => $this->user->id, 'wallets' => []];

    $result = $parser->parse($this->user->id, 'set budget kategori belanja 1 jt setiap bulan', $context);

    expect($result['intent'])->toBe('parse_budget')
        ->and($result['entities']['amount'])->toBe(1000000)
        ->and($result['entities']['category_name'])->toBe('Belanja')
        ->and($result['missing_fields'])->toBeEmpty();
});

test('set budget buat kategori investasi does not trigger investment block via orchestrator', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'set budget buat kategori investasi 1jt setiap bulan',
        'telegram',
        array_merge($this->context, ['skip_collecting_draft' => true])
    );

    expect($result['message'])->not->toContain('rekomendasi final')
        ->and($result['type'])->toBeIn(['confirmation_required', 'clarification']);
});

test('help command lists features', function () {
    $result = $this->router->handle($this->user->id, '/help', $this->context);

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toBe(TelegramBotMessages::help())
        ->and($result['message'])->toContain('/wallets')
        ->and($result['message'])->toContain('/addwallet')
        ->and($result['message'])->toContain('/transfer')
        ->and($result['message'])->toContain('/budget')
        ->and($result['message'])->toContain('/recurring');
});

test('addwallet command returns parser-aligned tutorial', function () {
    $result = $this->router->handle($this->user->id, '/addwallet', $this->context);

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toBe(TelegramBotMessages::addWalletTutorial())
        ->and($result['message'])->toContain('buat wallet e-wallet nama GOPAY');
});

test('transfer command returns parser-aligned tutorial', function () {
    $result = $this->router->handle($this->user->id, '/transfer', $this->context);

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toBe(TelegramBotMessages::transferTutorial())
        ->and($result['message'])->toContain('transfer 20 ribu dari GOPAY');
});

test('transaction alias transaksi returns tutorial', function () {
    $result = $this->router->handle($this->user->id, '/transaksi', $this->context);

    expect($result['message'])->toBe(TelegramBotMessages::transactionTutorial());
});

test('budget alias anggaran returns tutorial', function () {
    $result = $this->router->handle($this->user->id, '/anggaran', $this->context);

    expect($result['message'])->toBe(TelegramBotMessages::budgetTutorial());
});

test('create wallet via router delegates to orchestrator', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = $this->router->handle(
        $this->user->id,
        'buat wallet e-wallet nama GOPAY saldo 348.455',
        $this->context
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['message'])->toMatch('/gopay/i');
});

test('specific saldo gopay when wallet missing returns not found style', function () {
    $result = $this->router->handle($this->user->id, 'saldo GOPAY berapa', $this->context);

    expect($result['type'])->toBe('answer');
});

test('all saldo gw berapa returns all wallets', function () {
    $result = $this->router->handle($this->user->id, 'saldo gw berapa sekarang', $this->context);

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toContain('Total saldo');
});
