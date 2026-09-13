<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $customers = Customer::where('tenant_id', $tenantId)
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone ?? 'N/A',
                'email' => $c->email ?? '',
                'group' => $c->group ?? 'Retail',
                'creditLimit' => (float) $c->credit_limit,
                'currentBalance' => (float) $c->current_balance,
                'status' => $c->status,
                'notes' => $c->notes,
            ]);

        return Inertia::render('customers/Index', [
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'group' => 'required|string|in:Retail,Wholesale,VIP,Corporate',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        Customer::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'current_balance' => 0,
            'status' => 'Active',
        ]);

        return back()->with('success', 'Customer account created successfully.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($customer->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'group' => 'required|string|in:Retail,Wholesale,VIP,Corporate',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:Active,Inactive,Suspended',
            'notes' => 'nullable|string',
        ]);

        $customer->update($validated);

        return back()->with('success', 'Customer account updated.');
    }

    public function recordPayment(Request $request, Customer $customer): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($customer->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $newBalance = max(0, (float) $customer->current_balance - (float) $validated['amount']);
        $customer->update(['current_balance' => $newBalance]);

        return back()->with('success', 'Customer payment recorded successfully.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($customer->tenant_id === $tenant->id, 403);

        $customer->delete();

        return back()->with('success', 'Customer account removed.');
    }
}
