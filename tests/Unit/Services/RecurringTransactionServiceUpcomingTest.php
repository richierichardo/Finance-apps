<?php

use App\Enums\TransactionCategory;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\RecurringTransactionService;
use Illuminate\Support\Str;

test('getUpcomingForUser returns future active recurring items', function () {
    $user = User::factory()->create(['username' => 'rr_'.Str::random(8)]);
    $category = Category::firstOrCreate(
        ['slug' => TransactionCategory::Bills->value],
        ['name' => 'Bills']
    );
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'name' => 'W',
        'type' => WalletType::Bank,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    RecurringTransaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 25,
        'description' => 'Rent',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
        'next_run_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $service = app(RecurringTransactionService::class);
    $rows = $service->getUpcomingForUser($user->id, 5);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['description'])->toBe('Rent')
        ->and($rows[0]['amount'])->toBe(25.0);
});
