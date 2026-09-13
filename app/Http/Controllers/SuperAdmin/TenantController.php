<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * List all tenants with their user counts.
     */
    public function index(): Response
    {
        $tenants = Tenant::withCount('users')
            ->latest()
            ->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'name'           => $t->name,
                'slug'           => $t->slug,
                'kra_pin'        => $t->kra_pin,
                'email'          => $t->email,
                'phone'          => $t->phone,
                'country'        => $t->country,
                'currency'       => $t->currency,
                'business_type'  => $t->business_type,
                'plan'           => $t->plan,
                'billing_cycle'  => $t->billing_cycle,
                'status'         => $t->status,
                'is_demo'        => (bool) $t->is_demo,
                'branch_count'   => $t->branch_count,
                'user_count'     => $t->users_count,
                'monthly_revenue' => $t->monthly_revenue,
                'storage_used_mb' => $t->storage_used_mb,
                'storage_quota_mb' => $t->storage_quota_mb,
                'enabled_modules' => $t->enabled_modules ?? [],
                'registered_at'  => $t->created_at?->toDateString(),
                'last_active'    => $t->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('super-admin/Tenants', [
            'tenants' => $tenants,
            'flash'   => [
                'success' => session('success'),
                'createdCredentials' => session('createdCredentials'),
            ],
        ]);
    }

    /**
     * Register a new tenant + create their Owner user account and default branch.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'nullable|string|max:100',
            'kra_pin'        => 'nullable|string|max:20',
            'email'          => 'required|email|unique:tenants,email',
            'phone'          => 'nullable|string|max:20',
            'country'        => 'nullable|string|max:50',
            'currency'       => 'nullable|string|max:5',
            'business_type'  => 'required|string',
            'plan'           => 'required|in:Basic,Pro,Enterprise',
            'billing_cycle'  => 'required|in:Weekly,Monthly,Quarterly,Semi-Annual,Annual',
            'branch_count'   => 'nullable|integer|min:1',
            'user_count'     => 'nullable|integer|min:1',
            'owner_email'    => 'nullable|email',
            'owner_password' => 'nullable|string|min:6',
        ]);

        $baseSlug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        $allModules = [
            'pos', 'sales', 'products', 'inventory', 'purchases',
            'customers', 'suppliers', 'expenses', 'reports',
            'users', 'branches', 'settings',
        ];

        $tenantData = $validated;
        unset($tenantData['owner_email'], $tenantData['owner_password']);

        $tenant = Tenant::create([
            ...$tenantData,
            'slug'            => $slug,
            'status'          => 'Trial',
            'is_demo'         => false,
            'storage_quota_mb' => 5120,
            'enabled_modules' => $allModules,
        ]);

        $ownerEmail = !empty($validated['owner_email']) ? $validated['owner_email'] : $validated['email'];
        $ownerPassword = !empty($validated['owner_password']) ? $validated['owner_password'] : 'Owner@' . rand(1000, 9999) . '!';

        // Create the Owner user account for this tenant
        $owner = User::create([
            'name'              => $validated['name'] . ' Owner',
            'email'             => $ownerEmail,
            'password'          => Hash::make($ownerPassword),
            'tenant_id'         => $tenant->id,
            'role'              => 'Owner',
            'is_super_admin'    => false,
            'status'            => 'Active',
            'email_verified_at' => now(),
        ]);

        // Create primary default branch
        Branch::create([
            'tenant_id'       => $tenant->id,
            'name'            => $tenant->name . ' (Main HQ)',
            'code'            => 'HQ-01',
            'type'            => 'Main HQ',
            'location'        => $tenant->country ?? 'Main Location',
            'phone'           => $tenant->phone ?? 'N/A',
            'manager'         => $owner->name,
            'operating_hours' => '08:00 AM - 08:00 PM',
            'status'          => 'Active',
            'is_main'         => true,
        ]);

        $subdomainUrl = $tenant->getSubdomainUrl('/login');

        return back()->with([
            'success' => "Tenant \"{$tenant->name}\" registered successfully.",
            'createdCredentials' => [
                'tenant_name'    => $tenant->name,
                'subdomain'      => $tenant->slug,
                'subdomain_url'  => $subdomainUrl,
                'owner_email'    => $ownerEmail,
                'owner_password' => $ownerPassword,
            ],
        ]);
    }

    /**
     * Suspend a tenant (and all access for their users).
     */
    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'Suspended']);
        return back()->with('success', "\"{$tenant->name}\" has been suspended.");
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'Active']);
        return back()->with('success', "\"{$tenant->name}\" has been reactivated.");
    }

    /**
     * Permanently delete a tenant and ALL their data.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $name = $tenant->name;
        // Users cascade-deleted via nullOnDelete FK — they lose their tenant_id
        // For a hard delete, remove users first
        $tenant->users()->delete();
        $tenant->delete();

        return back()->with('success', "\"{$name}\" and all associated data have been deleted.");
    }

    /**
     * Update a tenant's profile, plan, and settings.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'email'             => 'sometimes|email|unique:tenants,email,' . $tenant->id,
            'phone'             => 'nullable|string|max:20',
            'kra_pin'           => 'nullable|string|max:20',
            'address'           => 'nullable|string|max:500',
            'country'           => 'nullable|string|max:50',
            'currency'          => 'nullable|string|max:5',
            'business_type'     => 'sometimes|string',
            'plan'              => 'sometimes|in:Basic,Pro,Enterprise',
            'billing_cycle'     => 'sometimes|in:Weekly,Monthly,Quarterly,Semi-Annual,Annual',
            'branch_count'      => 'nullable|integer|min:1',
            'user_count'        => 'nullable|integer|min:1',
            'storage_quota_mb'  => 'nullable|integer|min:512',
            'status'            => 'sometimes|in:Trial,Active,Suspended',
            'enabled_modules'   => 'nullable|array',
            'enabled_modules.*' => 'string',
        ]);

        $tenant->update($validated);

        return back()->with('success', "\"{$tenant->name}\" has been updated successfully.");
    }

    /**
     * Toggle a single module on/off for a tenant.
     */
    public function toggleModule(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'module' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        $module  = $request->input('module');
        $enabled = $request->boolean('enabled');
        $modules = $tenant->enabled_modules ?? [];

        if ($enabled && ! in_array($module, $modules)) {
            $modules[] = $module;
        } elseif (! $enabled) {
            $modules = array_values(array_filter($modules, fn($m) => $m !== $module));
        }

        $tenant->update(['enabled_modules' => $modules]);

        $label = $enabled ? 'enabled' : 'disabled';
        return back()->with('success', "Module \"{$module}\" has been {$label} for {$tenant->name}.");
    }
}
