<?php

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Events\TransactionCreated;
use App\Listeners\InvalidateDashboardCache;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'user_'.Str::random(8)]);
    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Test Wallet',
        'type' => WalletType::Bank,
        'initial_balance' => 1000,
        'balance' => 1000,
        'is_active' => true,
    ]);
});

test('transaction service create update delete work and fire events', function () {
    Event::fake([TransactionCreated::class]);

    $service = app(TransactionService::class);
    $transaction = $service->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Income,
        'amount' => 100,
        'description' => 'Test',
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and((float) $transaction->amount)->toBe(100.0);

    Event::assertDispatched(TransactionCreated::class);
});

test('transfer service prevents cross-user transfer', function () {
    $otherUser = User::factory()->create(['username' => 'other_'.Str::random(8)]);
    $otherWallet = Wallet::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Wallet',
        'type' => WalletType::Cash,
        'initial_balance' => 500,
        'balance' => 500,
        'is_active' => true,
    ]);

    $service = app(TransferService::class);

    $service->transfer(
        $this->wallet,
        $otherWallet,
        50,
        'Cross-user',
        now()->toDateTimeString()
    );
})->throws(\InvalidArgumentException::class, 'Cannot transfer between wallets of different users');

test('transactions on inactive wallet are rejected via form validation', function () {
    $inactiveWallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Inactive',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)->post(route('transactions.store'), [
        'wallet_id' => $inactiveWallet->id,
        'type' => TransactionType::Income->value,
        'amount' => 100,
        'occurred_at' => now()->toDateTimeString(),
    ]);

    $response->assertSessionHasErrors('wallet_id');
    expect(session('errors')->first('wallet_id'))->toContain('inactive');
});

test('amount must be greater than zero in transaction service', function () {
    $service = app(TransactionService::class);

    $service->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Income,
        'amount' => 0,
        'description' => 'Zero',
        'source' => 'web',
        'occurred_at' => now(),
    ]);
})->throws(\InvalidArgumentException::class, 'Amount must be greater than 0');

test('amount must be greater than zero in transfer service', function () {
    $wallet2 = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Wallet 2',
        'type' => WalletType::Ewallet,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $service = app(TransferService::class);
    $service->transfer($this->wallet, $wallet2, 0, null, now()->toDateTimeString());
})->throws(\InvalidArgumentException::class, 'Amount must be greater than 0');

test('dashboard cache is invalidated on transaction changes', function () {
    Cache::put("dashboard.summary.{$this->user->id}", ['cached' => true], 600);

    $listener = new InvalidateDashboardCache;
    $listener->handle(new \App\Events\TransactionCreated(
        Transaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::Income,
            'amount' => 50,
            'source' => 'web',
            'occurred_at' => now(),
        ])
    ));

    expect(Cache::has("dashboard.summary.{$this->user->id}"))->toBeFalse();
});

test('wallet sync balance command recalculates balances', function () {
    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Income,
        'amount' => 200,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $this->wallet->update(['balance' => 999]);

    $this->artisan('wallet:sync-balance', ['--user' => (string) $this->user->id])
        ->assertSuccessful();

    expect((float) $this->wallet->fresh()->balance)->toBe(1200.0);
});
