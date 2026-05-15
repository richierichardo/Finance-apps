<?php

use App\Enums\AIInsightType;
use App\Jobs\GenerateMonthlyInsightJob;
use App\Models\AIInsight;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'iu_'.Str::random(8)]);
    $this->other = User::factory()->create(['username' => 'io_'.Str::random(8)]);
});

test('insights routes require authentication', function (string $method, string $uri) {
    $response = match ($method) {
        'GET' => $this->getJson($uri),
        'POST' => $this->postJson($uri),
    };

    $response->assertUnauthorized();
})->with([
    ['GET', '/insights'],
    ['GET', '/insights/2026-01'],
    ['GET', '/insights/forecast'],
    ['POST', '/insights/generate'],
]);

test('insights index validates period_key format', function () {
    $this->actingAs($this->user)
        ->getJson('/insights?period_key=bad')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['period_key']]);
});

test('insights index validates type enum', function () {
    $this->actingAs($this->user)
        ->getJson('/insights?type=not_a_real_type')
        ->assertStatus(422)
        ->assertJsonPath('errors.type.0', 'Invalid insight type.');
});

test('insights index returns only insights for authenticated user', function () {
    AIInsight::create([
        'user_id' => $this->user->id,
        'type' => AIInsightType::MonthlySummary,
        'period_key' => '2026-01',
        'content' => 'Mine',
        'metadata' => null,
        'generated_at' => now(),
    ]);

    AIInsight::create([
        'user_id' => $this->other->id,
        'type' => AIInsightType::MonthlySummary,
        'period_key' => '2026-01',
        'content' => 'Secret',
        'metadata' => null,
        'generated_at' => now(),
    ]);

    $data = $this->actingAs($this->user)->getJson('/insights')->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['content'])->toBe('Mine');
});

test('insights show returns 422 for invalid period key', function () {
    $this->actingAs($this->user)
        ->getJson('/insights/not-a-period')
        ->assertStatus(422);
});

test('insights show returns 404 when no insight exists', function () {
    $this->actingAs($this->user)
        ->getJson('/insights/2099-01')
        ->assertNotFound();
});

test('insights show returns latest monthly insight', function () {
    AIInsight::create([
        'user_id' => $this->user->id,
        'type' => AIInsightType::MonthlySummary,
        'period_key' => '2026-02',
        'content' => 'Older',
        'metadata' => null,
        'generated_at' => now()->subHour(),
    ]);

    AIInsight::create([
        'user_id' => $this->user->id,
        'type' => AIInsightType::MonthlySummary,
        'period_key' => '2026-02',
        'content' => 'Newer',
        'metadata' => null,
        'generated_at' => now(),
    ]);

    $json = $this->actingAs($this->user)->getJson('/insights/2026-02')->assertOk()->json('data');

    expect($json['content'])->toBe('Newer');
});

test('insights generate dispatches job with user and period', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)->postJson('/insights/generate', [
        'period_key' => '2026-03',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('period_key', '2026-03');

    Bus::assertDispatched(GenerateMonthlyInsightJob::class, function (GenerateMonthlyInsightJob $job) {
        return $job->userId === $this->user->id && $job->periodKey === '2026-03';
    });
});

test('insights forecast returns structured payload', function () {
    $json = $this->actingAs($this->user)->getJson('/insights/forecast?period_key='.now()->format('Y-m'))
        ->assertOk()
        ->json('data');

    expect($json)->toHaveKey('forecast_available');
});
