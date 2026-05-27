<?php

namespace App\Services\AI;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiAccessService
{
    public function denialMessage(): string
    {
        return (string) config('ai.access.denial_message');
    }

    public function canUseAi(User $user): bool
    {
        if (! config('ai.access.feature_enabled', true)) {
            return false;
        }

        if ($user->ai_enabled) {
            return true;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        $allowedIds = config('ai.access.allowed_user_ids', []);
        if (in_array($user->id, $allowedIds, true)) {
            return true;
        }

        if (config('ai.access.public_access', false)) {
            return true;
        }

        return false;
    }

    public function canUseTelegram(User $user): bool
    {
        if (! config('telegram.feature_enabled', true)) {
            return false;
        }

        if ($user->telegram_enabled) {
            return true;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        $allowedIds = config('ai.access.allowed_user_ids', []);
        if (in_array($user->id, $allowedIds, true)) {
            return true;
        }

        if (config('telegram.public_access', false)) {
            return true;
        }

        return false;
    }

    public function canUseTelegramAi(User $user): bool
    {
        return $this->canUseTelegram($user) && $this->canUseAi($user);
    }

    /**
     * @return array{ok: false, code: string, message: string}
     */
    public function denialResponse(): array
    {
        return [
            'ok' => false,
            'code' => 'ai_access_denied',
            'message' => $this->denialMessage(),
        ];
    }

    public function logDenied(User $user, string $endpoint, string $channel = 'web'): void
    {
        Log::channel('single')->info('ai.access_denied', [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'channel' => $channel,
        ]);
    }
}
