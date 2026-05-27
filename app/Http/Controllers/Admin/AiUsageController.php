<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiUsageController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $base = fn () => AiMessage::query()->whereNotNull('total_tokens');

        $aggregate = function ($query) {
            return [
                'requests' => (int) (clone $query)->count(),
                'prompt_tokens' => (int) (clone $query)->sum('prompt_tokens'),
                'completion_tokens' => (int) (clone $query)->sum('completion_tokens'),
                'total_tokens' => (int) (clone $query)->sum('total_tokens'),
                'avg_latency_ms' => (int) round((float) (clone $query)->avg('latency_ms')),
            ];
        };

        $topUsers = AiMessage::query()
            ->whereNotNull('total_tokens')
            ->where('created_at', '>=', $monthStart)
            ->select('user_id', DB::raw('SUM(total_tokens) as tokens'), DB::raw('COUNT(*) as requests'))
            ->groupBy('user_id')
            ->orderByDesc('tokens')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $user = User::find($row->user_id);

                return [
                    'user_id' => $row->user_id,
                    'name' => $user?->name ?? 'Unknown',
                    'email' => $user?->email,
                    'tokens' => (int) $row->tokens,
                    'requests' => (int) $row->requests,
                ];
            });

        return Inertia::render('Admin/AiUsage/Index', [
            'summary' => [
                'today' => $aggregate($base()->where('created_at', '>=', $today)),
                'month' => $aggregate($base()->where('created_at', '>=', $monthStart)),
            ],
            'top_users' => $topUsers,
            'config_flags' => [
                'ai_feature_enabled' => config('ai.access.feature_enabled'),
                'ai_public_access' => config('ai.access.public_access'),
                'daily_request_limit' => config('ai.access.daily_request_limit'),
                'telegram_actions_per_minute' => config('ai.access.telegram_actions_per_minute'),
                'telegram_feature_enabled' => config('telegram.feature_enabled'),
                'telegram_public_access' => config('telegram.public_access'),
            ],
        ]);
    }
}
