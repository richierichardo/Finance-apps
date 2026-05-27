<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only('search'),
            'roles' => array_column(UserRole::cases(), 'value'),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load('telegramAccount');

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'roles' => array_column(UserRole::cases(), 'value'),
            'statuses' => array_column(UserStatus::cases(), 'value'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'ai_enabled' => ['sometimes', 'boolean'],
            'telegram_enabled' => ['sometimes', 'boolean'],
        ]);

        $user->update($validated);

        return back()->with('success', 'User updated.');
    }

    public function hardResetPassword(User $user): RedirectResponse
    {
        Gate::authorize('hard-reset-password');

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Password reset link sent to '.$user->email);
    }
}
