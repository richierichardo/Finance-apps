<?php

use App\Models\User;
use Illuminate\Support\Str;

test('admin dashboard is forbidden for member', function () {
    $member = User::factory()->create(['username' => 'mem_'.Str::random(8)]);

    $this->actingAs($member)->get(route('admin.dashboard'))->assertForbidden();
});

test('admin dashboard is accessible for admin role', function () {
    $admin = User::factory()->admin()->create(['username' => 'adm_'.Str::random(8)]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});

test('admin users index is accessible for admin', function () {
    $admin = User::factory()->admin()->create(['username' => 'adm2_'.Str::random(8)]);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});
