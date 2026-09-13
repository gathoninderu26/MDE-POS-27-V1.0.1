<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->latest()
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'contactPerson' => $s->contact_person ?? 'N/A',
                'phone' => $s->phone ?? 'N/A',
                'email' => $s->email ?? '',
                'address' => $s->address ?? '',
                'totalPurchases' => (float) $s->total_purchases,
                'outstandingBalance' => (float) $s->outstanding_balance,
                'status' => $s->status,
                'notes' => $s->notes,
            ]);

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Supplier::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'total_purchases' => 0,
            'outstanding_balance' => 0,
            'status' => 'Active',
        ]);

        return back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($supplier->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'status' => 'required|string|in:Active,Inactive,Suspended',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return back()->with('success', 'Supplier updated successfully.');
    }

    public function recordPayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($supplier->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $newBalance = max(0, (float) $supplier->outstanding_balance - (float) $validated['amount']);
        $supplier->update(['outstanding_balance' => $newBalance]);

        return back()->with('success', 'Supplier payment recorded successfully.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($supplier->tenant_id === $tenant->id, 403);

        $supplier->delete();

        return back()->with('success', 'Supplier removed.');
    }
}
