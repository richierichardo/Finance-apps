<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

test('categories index requires authentication', function () {
    $this->getJson(route('categories.index'))->assertUnauthorized();
});

test('authenticated user receives ordered categories json', function () {
    $user = User::factory()->create(['username' => 'cat_'.Str::random(8)]);

    Category::firstOrCreate(['slug' => 'alpha-cat'], ['name' => 'Alpha Cat']);

    $response = $this->actingAs($user)->getJson(route('categories.index'));

    $response->assertOk();
    expect($response->json())->toBeArray();
    expect($response->json()[0])->toHaveKeys(['id', 'name', 'slug']);
});
