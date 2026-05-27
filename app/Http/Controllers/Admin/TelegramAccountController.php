<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = TelegramAccount::query()
            ->with('user:id,name,email')
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status === 'active') {
                    $q->where('is_active', true)->whereNotNull('user_id');
                } elseif ($request->status === 'inactive') {
                    $q->where(function ($inner) {
                        $inner->where('is_active', false)->orWhereNull('user_id');
                    });
                }
            })
            ->orderByDesc('last_seen_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Telegram/Index', [
            'accounts' => $accounts,
            'filters' => $request->only('status'),
        ]);
    }

    public function unlink(TelegramAccount $telegramAccount): RedirectResponse
    {
        $telegramAccount->update([
            'user_id' => null,
            'is_active' => false,
            'linked_at' => null,
        ]);

        return back()->with('success', 'Telegram account unlinked.');
    }
}
