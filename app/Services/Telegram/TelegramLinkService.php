<?php

namespace App\Services\Telegram;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use Carbon\Carbon;

class TelegramLinkService
{
    /**
     * @return array{ok: bool, message: string, user_id: int|null}
     */
    public function linkAccount(string $code, string $telegramUserId, string $chatId, array $profile = []): array
    {
        $hash = hash('sha256', strtoupper(trim($code)));

        $token = TelegramLinkToken::query()
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return ['ok' => false, 'message' => 'Kode tidak valid atau sudah kedaluwarsa.', 'user_id' => null];
        }

        $token->update(['used_at' => now()]);

        TelegramAccount::query()->updateOrCreate(
            ['telegram_user_id' => $telegramUserId],
            [
                'user_id' => $token->user_id,
                'telegram_chat_id' => $chatId,
                'username' => $profile['username'] ?? null,
                'first_name' => $profile['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? null,
                'is_active' => true,
                'linked_at' => Carbon::now(),
                'last_seen_at' => Carbon::now(),
            ]
        );

        return [
            'ok' => true,
            'message' => 'Akun Flowlet berhasil terhubung!',
            'user_id' => $token->user_id,
        ];
    }

    public function findLinkedUserId(string $telegramUserId): ?int
    {
        $account = TelegramAccount::query()
            ->where('telegram_user_id', $telegramUserId)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->first();

        return $account?->user_id;
    }

    public function touchLastSeen(string $telegramUserId): void
    {
        TelegramAccount::query()
            ->where('telegram_user_id', $telegramUserId)
            ->update(['last_seen_at' => now()]);
    }
}
