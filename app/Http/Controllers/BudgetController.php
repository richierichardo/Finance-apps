<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;

class BudgetController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $budgets = Budget::forUser(auth()->id())
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($budgets);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        Budget::create([
            'user_id' => auth()->id(),
            'category_id' => $request->category_id,
            'amount' => $request->amount,
            'period' => $request->period,
            'start_date' => $request->start_date,
            'end_date' => null,
        ]);

        $this->invalidateBudgetCache();

        return Redirect::route('transactions.index')
            ->with('success', 'Budget created successfully.');
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $budget);

        $budget->update($request->validated());

        $this->invalidateBudgetCache();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Budget updated successfully.']);
        }
        return Redirect::back()->with('success', 'Budget updated successfully.');
    }

    public function destroy(Budget $budget): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        $this->invalidateBudgetCache();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Budget deleted successfully.']);
        }
        return Redirect::back()->with('success', 'Budget deleted successfully.');
    }

    private function invalidateBudgetCache(): void
    {
        Cache::forget('dashboard.budgets.' . auth()->id());
    }
}
