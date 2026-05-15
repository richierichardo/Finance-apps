<?php

use App\Jobs\GenerateMonthlyInsightJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

test('insights generate monthly command dispatches jobs for specified users', function () {
    Bus::fake();

    $user = User::factory()->create(['username' => 'cmd_'.Str::random(8)]);

    $this->artisan('insights:generate-monthly', [
        '--period' => '2026-01',
        '--users' => (string) $user->id,
    ])->assertSuccessful();

    Bus::assertDispatched(GenerateMonthlyInsightJob::class, function (GenerateMonthlyInsightJob $job) use ($user) {
        return $job->userId === $user->id && $job->periodKey === '2026-01';
    });
});
