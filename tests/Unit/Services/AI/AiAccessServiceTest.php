<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AI\AiAccessService;

beforeEach(function () {
    config([
        'ai.access.feature_enabled' => true,
        'ai.access.public_access' => false,
        'ai.access.allowed_user_ids' => [],
        'telegram.feature_enabled' => true,
        'telegram.public_access' => false,
    ]);
    $this->service = app(AiAccessService::class);
});

test('member without flags cannot use ai', function () {
    $user = User::factory()->create(['ai_enabled' => false]);

    expect($this->service->canUseAi($user))->toBeFalse();
});

test('owner can use ai', function () {
    $user = User::factory()->owner()->create();

    expect($this->service->canUseAi($user))->toBeTrue();
});

test('ai_enabled member can use ai', function () {
    $user = User::factory()->withAi()->create();

    expect($this->service->canUseAi($user))->toBeTrue();
});

test('kill switch disables ai for everyone', function () {
    config(['ai.access.feature_enabled' => false]);
    $user = User::factory()->owner()->create();

    expect($this->service->canUseAi($user))->toBeFalse();
});

test('allowed user ids grant ai access', function () {
    $user = User::factory()->create(['ai_enabled' => false]);
    config(['ai.access.allowed_user_ids' => [$user->id]]);

    expect($this->service->canUseAi($user))->toBeTrue();
});

test('public access flag allows any user', function () {
    config(['ai.access.public_access' => true]);
    $user = User::factory()->create(['ai_enabled' => false]);

    expect($this->service->canUseAi($user))->toBeTrue();
});

test('telegram ai requires both telegram and ai access', function () {
    $user = User::factory()->withAi()->create(['telegram_enabled' => false]);

    expect($this->service->canUseTelegramAi($user))->toBeFalse();
});
