<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\AI\AiAccessService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'is_admin' => fn () => ($user = $request->user()) && $user->role === UserRole::Admin,
            ],
            'features' => fn () => [
                'can_use_ai' => ($user = $request->user()) && app(AiAccessService::class)->canUseAi($user),
                'can_use_telegram' => ($user = $request->user()) && app(AiAccessService::class)->canUseTelegram($user),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
