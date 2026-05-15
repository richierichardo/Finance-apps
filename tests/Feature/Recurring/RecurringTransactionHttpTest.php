<?php

use App\Enums\TransactionCategoryExpenses;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'ru_'.Str::random(8)]);
    $this->other = User::factory()->create(['username' => 'ro_'.Str::random(8)]);

    $this->category = Category::firstOrCreate(
        ['slug' => TransactionCategoryExpenses::Bills->value],
        ['name' => 'Bills']
    );

    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'RW',
        'type' => WalletType::Bank,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);
});

test('recurring index requires auth', function () {
    $this->get(route('recurring-transactions.index'))->assertRedirect(route('login'));
});

test('user can create recurring transaction', function () {
    $response = $this->actingAs($this->user)->post(route('recurring-transactions.store'), [
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 99,
        'description' => 'Sub',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('recurring-transactions.index'));
    expect(RecurringTransaction::where('user_id', $this->user->id)->count())->toBe(1);
});

test('user cannot update another users recurring transaction', function () {
    $otherWallet = Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'OW',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $rec = RecurringTransaction::create([
        'user_id' => $this->other->id,
        'wallet_id' => $otherWallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 10,
        'description' => 'X',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
        'next_run_at' => now(),
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->put(route('recurring-transactions.update', $rec), [
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 20,
            'description' => 'Y',
            'frequency' => 'monthly',
            'interval' => 1,
            'start_date' => now()->format('Y-m-d'),
        ])
        ->assertForbidden();
});

test('user can toggle own recurring transaction', function () {
    $rec = RecurringTransaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 10,
        'description' => 'X',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
        'next_run_at' => now(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(
        route('recurring-transactions.toggle', $rec)
    );

    $response->assertRedirect(route('recurring-transactions.index'));
    expect($rec->fresh()->is_active)->toBeFalse();
});
