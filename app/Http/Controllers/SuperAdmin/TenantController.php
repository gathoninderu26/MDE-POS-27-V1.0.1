<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'kraPin' => $t->kra_pin,
                'kra_pin' => $t->kra_pin,
                'email' => $t->email,
                'phone' => $t->phone,
                'country' => $t->country,
                'currency' => $t->currency,
                'businessType' => $t->business_type,
                'business_type' => $t->business_type,
                'plan' => $t->plan,
                'billingCycle' => $t->billing_cycle,
                'billing_cycle' => $t->billing_cycle,
                'status' => $t->status,
                'is_demo' => (bool) $t->is_demo,
                'branchCount' => $t->branch_count,
                'branch_count' => $t->branch_count,
                'userCount' => $t->users_count,   // actual real count
                'user_count' => $t->users_count,
                'monthlyRevenue' => (float) $t->monthly_revenue,
                'monthly_revenue' => (float) $t->monthly_revenue,
                'storageUsedMB' => $t->storage_used_mb,
                'storage_used_mb' => $t->storage_used_mb,
                'storageQuotaMB' => $t->storage_quota_mb,
                'storage_quota_mb' => $t->storage_quota_mb,
                'enabledModules' => $t->enabled_modules ?? [],
                'enabled_modules' => $t->enabled_modules ?? [],
                'customModules' => $t->custom_modules ?? [],
                'device_policy' => $t->device_policy ?? [],
                'network_policy' => $t->network_policy ?? [],
                'settings' => $t->settings ?? [],
                'is_multi_branch' => $t->isMultiBranch(),
                'registeredAt' => $t->created_at?->toDateString(),
                'registered_at' => $t->created_at?->toDateString(),
                'lastActive' => $t->updated_at?->diffForHumans(),
                'last_active' => $t->updated_at?->diffForHumans(),
                'nextBillingDate' => $t->next_billing_date?->toDateString(),
                'next_billing_date' => $t->next_billing_date?->toDateString(),
                // Alias for back-compat
                'subdomain' => $t->slug,
                'subdomain_url' => $t->getSubdomainUrl(),
            ]);

        return Inertia::render('super-admin/Tenants', [
            'tenants' => $tenants,
            'flash' => [
                'success' => session('success'),
                'createdCredentials' => session('createdCredentials'),
            ],
        ]);
    }

    /**
     * Display the module manager with live tenant records from the database.
     */
    public function modules(): Response
    {
        $tenants = Tenant::withCount('users')
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => (string) $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'kraPin' => $t->kra_pin,
                'email' => $t->email,
                'phone' => $t->phone,
                'country' => $t->country,
                'currency' => $t->currency,
                'plan' => $t->plan,
                'status' => $t->status,
                'businessType' => $t->business_type,
                'business_type' => $t->business_type,
                'userCount' => $t->users_count,
                'branchCount' => $t->branch_count ?? 1,
                'monthlyRevenue' => (float) $t->monthly_revenue,
                'billingCycle' => $t->billing_cycle ?? 'Monthly',
                'nextBillingDate' => $t->next_billing_date?->toDateString() ?? now()->addMonth()->toDateString(),
                'registeredAt' => $t->created_at?->toDateString(),
                'lastActive' => $t->updated_at?->diffForHumans(),
                'storageUsedMB' => $t->storage_used_mb ?? 0,
                'storageQuotaMB' => $t->storage_quota_mb ?? 5120,
                'enabledModules' => $t->enabled_modules ?? [],
                'enabled_modules' => $t->enabled_modules ?? [],
                'customModules' => $t->custom_modules ?? [],
                'is_multi_branch' => $t->isMultiBranch(),
            ]);

        return Inertia::render('super-admin/ModuleManager', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Register a new tenant + create their Owner user account and default branch using TenantProvisioningService.
     */
    public function store(Request $request, TenantProvisioningService $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100',
            'kra_pin' => 'nullable|string|max:20',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:5',
            'business_type' => 'required|string',
            'plan' => 'required|in:Basic,Pro,Enterprise',
            'billing_cycle' => 'required|in:Weekly,Monthly,Quarterly,Semi-Annual,Annual',
            'branch_count' => 'nullable|integer|min:1',
            'user_count' => 'nullable|integer|min:1',
            'owner_email' => 'nullable|email',
            'owner_password' => 'nullable|string|min:6',
            // Device & Hardware binding
            'selected_device_types' => 'nullable|array',
            'selected_device_types.*' => 'string',
            'require_device_binding' => 'nullable|boolean',
            'pos_hardware_count' => 'nullable|integer|min:1',
            'single_session_per_cashier' => 'nullable|boolean',
            // Network & Connection security
            'block_vpn' => 'nullable|boolean',
            'require_store_wifi' => 'nullable|boolean',
            'primary_store_wifi_ssid' => 'nullable|string|max:100',
            'block_hotspot' => 'nullable|boolean',
            'cellular_policy' => 'nullable|string|in:allow_all,field_roles_only,block_cellular',
            // Module overrides
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => 'string',
            // Payment channels configuration
            'enable_cash' => 'nullable|boolean',
            'cash_drawer_label' => 'nullable|string|max:100',
            'enable_mpesa_till' => 'nullable|boolean',
            'mpesa_till_number' => 'nullable|string|max:50',
            'mpesa_store_name' => 'nullable|string|max:100',
            'enable_mpesa_paybill' => 'nullable|boolean',
            'mpesa_paybill_number' => 'nullable|string|max:50',
            'mpesa_paybill_account' => 'nullable|string|max:100',
            'enable_card' => 'nullable|boolean',
            'card_terminal_id' => 'nullable|string|max:100',
            'card_bank_name' => 'nullable|string|max:100',
            'enable_bank' => 'nullable|boolean',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'enable_credit' => 'nullable|boolean',
            'payment_methods' => 'nullable|array',
        ]);

        $result = $provisioner->provision($validated);

        return back()->with([
            'success' => "Tenant \"{$result['tenant']->name}\" registered successfully with complete security and module configuration.",
            'createdCredentials' => $result['credentials'],
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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenants,email,'.$tenant->id,
            'phone' => 'nullable|string|max:20',
            'kra_pin' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'country' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:5',
            'business_type' => 'sometimes|string',
            'plan' => 'sometimes|in:Basic,Pro,Enterprise',
            'billing_cycle' => 'sometimes|in:Weekly,Monthly,Quarterly,Semi-Annual,Annual',
            'branch_count' => 'nullable|integer|min:1',
            'user_count' => 'nullable|integer|min:1',
            'storage_quota_mb' => 'nullable|integer|min:512',
            'status' => 'sometimes|in:Trial,Active,Suspended',
            'enabled_modules' => 'nullable|array',
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

        $module = $request->input('module');
        $enabled = $request->boolean('enabled');
        $modules = $tenant->enabled_modules ?? [];

        if ($enabled && ! in_array($module, $modules)) {
            $modules[] = $module;
        } elseif (! $enabled) {
            $modules = array_values(array_filter($modules, fn ($m) => $m !== $module));
        }

        $tenant->update(['enabled_modules' => $modules]);

        $label = $enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Module \"{$module}\" has been {$label} for {$tenant->name}.");
    }

    /**
     * Direct one-click login into a tenant workspace as the Owner.
     */
    public function impersonate(Tenant $tenant): RedirectResponse
    {
        $owner = $tenant->users()->where('role', 'Owner')->first() ?? $tenant->users()->first();

        if (! $owner) {
            return back()->with('error', "No user account exists for {$tenant->name}.");
        }

        $adminId = Auth::id();

        // Cleanly switch session from Super Admin to Tenant Owner
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        Auth::guard('web')->login($owner);
        request()->session()->regenerate();

        if ($adminId) {
            request()->session()->put('impersonated_by_admin_id', $adminId);
        }
        request()->session()->put('active_tenant_id', $tenant->id);
        request()->session()->put('active_tenant_slug', $tenant->slug);
        app()->instance('currentTenant', $tenant);

        if ($owner->role === 'Cashier') {
            return redirect()->route('pos');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Revert impersonation back to the Super Admin session.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('impersonated_by_admin_id');

        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($adminId);
        if (! $admin || ! $admin->isSuperAdmin()) {
            return redirect()->route('login');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return redirect()->route('super-admin.tenants')->with('success', 'Returned to Super Admin console.');
    }
}
