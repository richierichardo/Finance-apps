<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class AiRateLimitService
{
    public function checkDailyLimit(int $userId): bool
    {
        $limit = (int) config('ai.access.daily_request_limit', 20);
        if ($limit <= 0) {
            return true;
        }

        $key = 'ai.requests.'.$userId.'.'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);

        return $count < $limit;
    }

    public function hitDailyLimit(int $userId): void
    {
        $key = 'ai.requests.'.$userId.'.'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->endOfDay());
    }

    public function dailyLimitMessage(): string
    {
        $limit = (int) config('ai.access.daily_request_limit', 20);

        return "Batas penggunaan AI harian tercapai ({$limit} permintaan/hari). Coba lagi besok.";
    }

    public function checkTelegramMinuteLimit(int $userId): bool
    {
        $limit = (int) config('ai.access.telegram_actions_per_minute', 5);
        if ($limit <= 0) {
            return true;
        }

        $key = 'ai.telegram.'.$userId.'.'.now()->format('Y-m-d-H-i');
        $count = (int) Cache::get($key, 0);

        return $count < $limit;
    }

    public function hitTelegramMinuteLimit(int $userId): void
    {
        $key = 'ai.telegram.'.$userId.'.'.now()->format('Y-m-d-H-i');
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addMinute());
    }

    public function telegramLimitMessage(): string
    {
        $limit = (int) config('ai.access.telegram_actions_per_minute', 5);

        return "Terlalu banyak permintaan AI via Telegram. Tunggu sebentar (maks {$limit}/menit).";
    }

    /**
     * @return array{ok: false, code: string, message: string}
     */
    public function rateLimitResponse(string $message): array
    {
        return [
            'ok' => false,
            'code' => 'ai_rate_limit_exceeded',
            'message' => $message,
        ];
    }
}
