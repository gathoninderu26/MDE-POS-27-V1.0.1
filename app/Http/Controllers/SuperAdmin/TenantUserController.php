<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantUserController extends Controller
{
    /**
     * List all users belonging to a tenant.
     */
    public function index(Tenant $tenant): Response
    {
        $users = $tenant->users()
            ->orderByRaw("CASE role
                WHEN 'Owner' THEN 1
                WHEN 'Administrator' THEN 2
                WHEN 'Manager' THEN 3
                WHEN 'Supervisor' THEN 4
                WHEN 'Cashier' THEN 5
                WHEN 'Inventory Manager' THEN 6
                WHEN 'Accountant' THEN 7
                ELSE 8 END")
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'phone'      => $u->phone,
                'status'     => $u->status ?? 'Active',
                'created_at' => $u->created_at?->toDateString(),
                'last_login' => $u->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('super-admin/TenantUsers', [
            'tenant' => [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'slug'          => $tenant->slug,
                'email'         => $tenant->email,
                'business_type' => $tenant->business_type,
                'plan'          => $tenant->plan,
                'status'        => $tenant->status,
            ],
            'users' => $users,
        ]);
    }

    /**
     * Create a new user under this tenant.
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|in:Owner,Administrator,Manager,Supervisor,Cashier,Inventory Manager,Accountant',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'password'         => Hash::make($validated['password']),
            'tenant_id'        => $tenant->id,
            'role'             => $validated['role'],
            'phone'            => $validated['phone'] ?? null,
            'is_super_admin'   => false,
            'status'           => 'Active',
            'email_verified_at' => now(),
        ]);

        return back()->with('success', "{$validated['name']} has been added to {$tenant->name}.");
    }

    /**
     * Update a tenant user's role, name, phone, or status.
     */
    public function update(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        // Ensure this user belongs to this tenant
        abort_unless($user->tenant_id === $tenant->id, 403, 'User does not belong to this tenant.');

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'role'   => 'sometimes|in:Owner,Administrator,Manager,Supervisor,Cashier,Inventory Manager,Accountant',
            'phone'  => 'nullable|string|max:20',
            'status' => 'sometimes|in:Active,Suspended',
        ]);

        $user->update($validated);

        return back()->with('success', "{$user->name} has been updated.");
    }

    /**
     * Reset a tenant user's password. Returns the temporary password.
     */
    public function resetPassword(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->id, 403, 'User does not belong to this tenant.');

        $tempPassword = Str::password(12, symbols: false);
        $user->update(['password' => Hash::make($tempPassword)]);

        return back()->with('success', "Password reset. Temporary password: {$tempPassword}");
    }

    /**
     * Delete a tenant user permanently.
     */
    public function destroy(Tenant $tenant, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->id, 403, 'User does not belong to this tenant.');

        // Prevent deleting the last owner
        if ($user->role === 'Owner' && $tenant->users()->where('role', 'Owner')->count() <= 1) {
            return back()->withErrors(['error' => 'Cannot delete the last Owner of a tenant.']);
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "{$name} has been permanently removed from {$tenant->name}.");
    }
}
