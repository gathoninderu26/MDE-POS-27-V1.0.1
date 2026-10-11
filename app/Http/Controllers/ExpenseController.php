<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $expenses = Expense::where('tenant_id', $tenantId)
            ->with(['branch', 'user'])
            ->latest('date')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'category' => $e->category,
                'amount' => (float) $e->amount,
                'date' => $e->date->format('Y-m-d'),
                'payment_method' => $e->payment_method,
                'description' => $e->description,
                'branch' => $e->branch?->name ?? 'General HQ',
                'logged_by' => $e->user?->name ?? 'Admin',
            ]);

        $branches = Branch::where('tenant_id', $tenantId)->get(['id', 'name']);

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'payment_method' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
            'description' => 'nullable|string',
        ]);

        Expense::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($expense->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'payment_method' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
            'description' => 'nullable|string',
        ]);

        $expense->update($validated);

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($expense->tenant_id === $tenant->id, 403);

        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }
}
