<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AIUsageController extends Controller
{
    public function summary(): JsonResponse
    {
        $userId = auth()->id();
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $baseQuery = fn () => AiMessage::query()
            ->where('user_id', $userId)
            ->whereNotNull('total_tokens');

        $aggregate = fn ($query) => [
            'requests' => (int) (clone $query)->count(),
            'prompt_tokens' => (int) (clone $query)->sum('prompt_tokens'),
            'completion_tokens' => (int) (clone $query)->sum('completion_tokens'),
            'total_tokens' => (int) (clone $query)->sum('total_tokens'),
        ];

        $todayQuery = $baseQuery()->where('created_at', '>=', $today);
        $monthQuery = $baseQuery()->where('created_at', '>=', $monthStart);

        $byChannel = AiMessage::query()
            ->where('user_id', $userId)
            ->whereNotNull('total_tokens')
            ->where('created_at', '>=', $monthStart)
            ->select('channel', DB::raw('SUM(total_tokens) as tokens'))
            ->groupBy('channel')
            ->pluck('tokens', 'channel')
            ->map(fn ($v) => (int) $v)
            ->all();

        return response()->json([
            'today' => $aggregate($todayQuery),
            'this_month' => $aggregate($monthQuery),
            'by_channel' => $byChannel,
        ]);
    }
}
