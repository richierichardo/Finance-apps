<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Transaction::query()
            ->with(['wallet:id,name', 'user:id,name,email'])
            ->orderByDesc('occurred_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $transactions = $query->paginate(30)->withQueryString();

        return Inertia::render('Admin/Audit/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['user_id', 'source', 'type']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
