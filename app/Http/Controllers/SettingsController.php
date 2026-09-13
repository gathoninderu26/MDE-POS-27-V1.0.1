<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);

        return Inertia::render('settings/SettingsIndex', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'kra_pin' => $tenant->kra_pin ?? '',
                'email' => $tenant->email,
                'phone' => $tenant->phone ?? '',
                'address' => $tenant->address ?? '',
                'currency' => $tenant->currency ?? 'KES',
                'business_type' => $tenant->business_type ?? 'Supermarket',
                'plan' => $tenant->plan,
                'settings' => $tenant->settings ?? [],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kra_pin' => 'nullable|string|max:50',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'business_type' => 'required|string|max:100',
            'settings' => 'nullable|array',
        ]);

        $tenant->update([
            'name' => $validated['name'],
            'kra_pin' => $validated['kra_pin'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'business_type' => $validated['business_type'],
            'settings' => array_merge($tenant->settings ?? [], $validated['settings'] ?? []),
        ]);

        return back()->with('success', 'Store settings updated successfully.');
    }
}
