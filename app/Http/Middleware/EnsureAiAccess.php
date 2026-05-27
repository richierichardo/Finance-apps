<?php

namespace App\Http\Middleware;

use App\Services\AI\AiAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiAccess
{
    public function __construct(
        protected AiAccessService $aiAccessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->aiAccessService->canUseAi($user)) {
            if ($user) {
                $this->aiAccessService->logDenied($user, $request->path());
            }

            return response()->json(
                $this->aiAccessService->denialResponse(),
                403
            );
        }

        return $next($request);
    }
}
