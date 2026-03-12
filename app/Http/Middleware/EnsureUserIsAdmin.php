<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if user is admin
        if ($user->role !== UserRole::Admin) {
            abort(403, 'Unauthorized - Admin access required');
        }

        // Check if user is not banned
        if ($user->status === UserStatus::Banned) {
            abort(403, 'User is banned');
        }

        // Check if user is not banned until future date
        if ($user->banned_until && $user->banned_until->isFuture()) {
            abort(403, 'User is temporarily banned until ' . $user->banned_until->format('Y-m-d H:i:s'));
        }

        // Check if user is active
        if ($user->status !== UserStatus::Active) {
            abort(403, 'User account is not active');
        }

        return $next($request);
    }
}
