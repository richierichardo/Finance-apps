<?php

use App\Models\User;
use Illuminate\Support\Str;

test('admin is redirected from member dashboard to admin dashboard', function () {
    $admin = User::factory()->admin()->create(['username' => 'adm_lock_'.Str::random(6)]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('admin is redirected from wallets to admin dashboard', function () {
    $admin = User::factory()->admin()->create(['username' => 'adm_lock2_'.Str::random(6)]);

    $this->actingAs($admin)
        ->get(route('wallets.index'))
        ->assertRedirect(route('admin.dashboard'));
});

test('admin can still access profile', function () {
    $admin = User::factory()->admin()->create(['username' => 'adm_prof_'.Str::random(6)]);

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('member can access dashboard', function () {
    $member = User::factory()->create(['username' => 'mem_dash_'.Str::random(6)]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk();
});
