<?php

namespace App\Http\Controllers;

use App\Models\AiActionDraft;
use App\Services\AI\FinanceAIOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    public function __construct(
        protected FinanceAIOrchestratorService $orchestrator,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->orchestrator->handleUserMessage(
            (int) auth()->id(),
            $validated['message'],
            'dashboard',
        );

        return response()->json($result);
    }

    public function confirm(Request $request, AiActionDraft $draft): JsonResponse
    {
        $this->authorize('confirm', $draft);

        $result = $this->orchestrator->confirmDraft((int) auth()->id(), $draft);

        return response()->json($result);
    }

    public function cancel(Request $request, AiActionDraft $draft): JsonResponse
    {
        $this->authorize('cancel', $draft);

        $result = $this->orchestrator->cancelDraft((int) auth()->id(), $draft);

        return response()->json($result);
    }
}
