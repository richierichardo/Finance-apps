<?php

use App\Enums\TransactionCategoryExpenses;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'tu_'.Str::random(8)]);
    $this->other = User::factory()->create(['username' => 'to_'.Str::random(8)]);

    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'W1',
        'type' => WalletType::Bank,
        'initial_balance' => 500,
        'balance' => 500,
        'is_active' => true,
    ]);

    $this->otherWallet = Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'Other',
        'type' => WalletType::Cash,
        'initial_balance' => 100,
        'balance' => 100,
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => TransactionCategoryExpenses::Food->value],
        ['name' => 'Food']
    );
});

test('transaction index requires auth', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});

test('transaction index supports filters', function () {
    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 25,
        'category_transaction' => TransactionCategoryExpenses::Food->value,
        'source' => 'web',
        'occurred_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.index', [
        'wallet_id' => $this->wallet->id,
        'type' => 'expense',
    ]));

    $response->assertOk();
});

test('user can store expense transaction', function () {
    $response = $this->actingAs($this->user)->post(route('transactions.store'), [
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Expense->value,
        'amount' => 50,
        'category_transaction' => TransactionCategoryExpenses::Food->value,
        'description' => 'Lunch',
        'occurred_at' => now()->format('Y-m-d H:i:s'),
    ]);

    $response->assertRedirect(route('transactions.index'));
    expect((float) $this->wallet->fresh()->balance)->toBe(450.0);
});

test('transaction store rejects inactive wallet', function () {
    $this->wallet->update(['is_active' => false]);

    $response = $this->actingAs($this->user)->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::Expense->value,
            'amount' => 10,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ]);

    $response->assertSessionHasErrors('wallet_id');
});

test('user cannot view transaction belonging to another user', function () {
    $tx = Transaction::create([
        'user_id' => $this->other->id,
        'wallet_id' => $this->otherWallet->id,
        'type' => TransactionType::Expense,
        'amount' => 5,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)->get(route('transactions.show', $tx))->assertForbidden();
});

test('user can transfer between own wallets', function () {
    $w2 = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'W2',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('transactions.transfer'), [
        'from_wallet_id' => $this->wallet->id,
        'to_wallet_id' => $w2->id,
        'amount' => 100,
        'occurred_at' => now()->format('Y-m-d H:i:s'),
    ]);

    $response->assertRedirect(route('transactions.index'));
    expect((float) $this->wallet->fresh()->balance)->toBe(400.0)
        ->and((float) $w2->fresh()->balance)->toBe(100.0);
});

test('transfer rejects when from wallet belongs to another user', function () {
    $w2 = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'W2',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->from(route('transactions.transfer.form'))
        ->post(route('transactions.transfer'), [
            'from_wallet_id' => $this->otherWallet->id,
            'to_wallet_id' => $w2->id,
            'amount' => 10,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ]);

    $response->assertSessionHasErrors('from_wallet_id');
});
