<?php

use App\Enums\TransactionCategoryExpenses;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'bu_'.Str::random(8)]);
    $this->other = User::factory()->create(['username' => 'bo_'.Str::random(8)]);

    $this->category = Category::firstOrCreate(
        ['slug' => TransactionCategoryExpenses::Food->value],
        ['name' => 'Food']
    );
});

test('budget index returns json for authenticated user', function () {
    Budget::create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'amount' => 500,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth(),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('budgets.index'));

    $response->assertOk()->assertJsonFragment(['amount' => '500.00']);
});

test('user cannot update another users budget', function () {
    $budget = Budget::create([
        'user_id' => $this->other->id,
        'category_id' => $this->category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth(),
    ]);

    $this->actingAs($this->user)
        ->putJson(route('budgets.update', $budget), [
            'amount' => 999,
            'period' => 'monthly',
            'start_date' => $budget->start_date->format('Y-m-d'),
        ])
        ->assertForbidden();
});

test('user cannot delete another users budget', function () {
    $budget = Budget::create([
        'user_id' => $this->other->id,
        'category_id' => $this->category->id,
        'amount' => 100,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson(route('budgets.destroy', $budget))
        ->assertForbidden();
});

test('budget store rejects invalid category', function () {
    $response = $this->actingAs($this->user)->from(route('transactions.index'))
        ->post(route('budgets.store'), [
            'category_id' => 999999,
            'amount' => 100,
            'period' => 'monthly',
        ]);

    $response->assertSessionHasErrors('category_id');
});
