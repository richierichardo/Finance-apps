<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyInsightJob;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyInsightsCommand extends Command
{
    protected $signature = 'insights:generate-monthly {--period=} {--users=}';

    protected $description = 'Dispatch monthly AI insight jobs for users';

    public function handle(): int
    {
        $periodKey = $this->resolvePeriodKey();
        [$start, $end] = $this->resolveRangeFromPeriod($periodKey);

        $userIds = $this->resolveUserIds($start, $end);

        if (empty($userIds)) {
            $this->info('No users found for insight generation.');

            return Command::SUCCESS;
        }

        foreach ($userIds as $userId) {
            GenerateMonthlyInsightJob::dispatch((int) $userId, $periodKey);
        }

        $this->info('Queued monthly insight jobs for '.count($userIds).' user(s) period '.$periodKey.'.');

        return Command::SUCCESS;
    }

    private function resolvePeriodKey(): string
    {
        $format = config('insights.period_key_format', 'Y-m');
        $period = $this->option('period');

        if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $period;
        }

        return Carbon::now()->subMonth()->format($format);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRangeFromPeriod(string $periodKey): array
    {
        $start = Carbon::createFromFormat(config('insights.period_key_format', 'Y-m'), $periodKey)
            ->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIds(Carbon $start, Carbon $end): array
    {
        $users = $this->option('users');
        if (is_string($users) && trim($users) !== '') {
            return collect(explode(',', $users))
                ->map(fn (string $id) => (int) trim($id))
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
        }

        $activeUserIds = Transaction::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->distinct()
            ->pluck('user_id')
            ->values()
            ->all();

        if (! empty($activeUserIds)) {
            return array_map('intval', $activeUserIds);
        }

        return User::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
