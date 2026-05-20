<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TelegramLinkController extends Controller
{
    public function store(): JsonResponse
    {
        $userId = (int) auth()->id();
        $expiryMinutes = (int) config('ai.link_token_expiry_minutes', 10);

        TelegramLinkToken::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->delete();

        $plainToken = strtoupper(Str::random(6));
        $token = TelegramLinkToken::create([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        return response()->json([
            'code' => $plainToken,
            'expires_at' => $token->expires_at->toIso8601String(),
            'expires_in_minutes' => $expiryMinutes,
            'instruction' => 'Kirim ke bot Telegram: /link '.$plainToken,
        ]);
    }
}
