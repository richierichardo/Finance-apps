<?php

namespace App\Http\Controllers;

use App\Enums\AIInsightType;
use App\Jobs\GenerateMonthlyInsightJob;
use App\Models\AIInsight;
use App\Services\ForecastInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIInsightController extends Controller
{
    public function __construct(
        protected ForecastInsightService $forecastInsightService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'period_key' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'type' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = (int) auth()->id();
        $periodKey = $request->query('period_key');
        $typeInput = $request->query('type');

        $query = AIInsight::query()
            ->forUser($userId)
            ->latest('generated_at');

        if ($periodKey) {
            $query->forPeriod((string) $periodKey);
        }

        if ($typeInput) {
            $type = AIInsightType::tryFrom((string) $typeInput);
            if (! $type) {
                return response()->json(['errors' => ['type' => ['Invalid insight type.']]], 422);
            }
            $query->ofType($type);
        }

        return response()->json([
            'data' => $query->limit(50)->get(),
        ]);
    }

    public function show(string $periodKey): JsonResponse
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
            return response()->json(['message' => 'Invalid period key format.'], 422);
        }

        $insight = AIInsight::findLatestMonthly((int) auth()->id(), $periodKey);

        if (! $insight) {
            return response()->json(['message' => 'Insight not found.'], 404);
        }

        return response()->json(['data' => $insight]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period_key' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $periodKey = (string) $request->input(
            'period_key',
            now()->format(config('insights.period_key_format', 'Y-m'))
        );

        GenerateMonthlyInsightJob::dispatch((int) auth()->id(), $periodKey);

        return response()->json([
            'message' => 'Insight generation queued.',
            'period_key' => $periodKey,
        ], 202);
    }

    public function forecast(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'period_key' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $periodKey = (string) $request->query(
            'period_key',
            now()->format(config('insights.period_key_format', 'Y-m'))
        );

        return response()->json([
            'data' => $this->forecastInsightService->generateForecastInsight((int) auth()->id(), $periodKey),
        ]);
    }
}
