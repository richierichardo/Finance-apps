<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Check if user is banned
        if ($user->status === UserStatus::Banned) {
            Auth::logout();
            $request->session()->invalidate();
            
            throw ValidationException::withMessages([
                'login' => 'Your account has been banned.',
            ]);
        }

        // Check if user is banned until a future date
        if ($user->banned_until && $user->banned_until->isFuture()) {
            Auth::logout();
            $request->session()->invalidate();
            
            throw ValidationException::withMessages([
                'login' => 'Your account is temporarily banned until ' . $user->banned_until->format('Y-m-d H:i:s'),
            ]);
        }

        // Check if user is not active (suspended)
        if ($user->status !== UserStatus::Active) {
            Auth::logout();
            $request->session()->invalidate();
            
            throw ValidationException::withMessages([
                'login' => 'Your account is not active. Please contact support.',
            ]);
        }

        // Check email verified for member only
        if ($user->role === UserRole::Member && !$user->email_verified_at) {
            Auth::logout();
            $request->session()->invalidate();
            
            session(['registration_email' => $user->email]);
            return redirect()->route('otp.verify');
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        // Redirect based on role
        $redirectTo = $user->role === UserRole::Admin
            ? route('admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($redirectTo);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
