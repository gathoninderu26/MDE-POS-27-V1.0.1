<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::withCount('users')->latest()->get();

        $mappedTenants = $tenants->map(fn($t) => [
            'id'             => $t->id,
            'name'           => $t->name,
            'slug'           => $t->slug,
            'kraPin'         => $t->kra_pin,
            'kra_pin'        => $t->kra_pin,
            'email'          => $t->email,
            'phone'          => $t->phone,
            'businessType'   => $t->business_type,
            'business_type'  => $t->business_type,
            'plan'           => $t->plan,
            'billingCycle'   => $t->billing_cycle,
            'billing_cycle'  => $t->billing_cycle,
            'status'         => $t->status,
            'is_demo'        => (bool) $t->is_demo,
            'branchCount'    => $t->branch_count,
            'branch_count'   => $t->branch_count,
            'userCount'      => $t->users_count,
            'user_count'     => $t->users_count,
            'monthlyRevenue' => (float) $t->monthly_revenue,
            'monthly_revenue' => (float) $t->monthly_revenue,
            'storageUsedMB'  => $t->storage_used_mb,
            'storageQuotaMB' => $t->storage_quota_mb,
            'lastActive'     => $t->updated_at?->diffForHumans(),
            'registeredAt'   => $t->created_at?->toDateString(),
            'nextBillingDate' => $t->next_billing_date?->toDateString(),
            'subdomain_url'  => $t->getSubdomainUrl(),
        ]);

        $stats = [
            'total_tenants'     => $tenants->count(),
            'active_tenants'    => $tenants->where('status', 'Active')->count(),
            'suspended_tenants' => $tenants->where('status', 'Suspended')->count(),
            'trial_tenants'     => $tenants->where('status', 'Trial')->count(),
            'demo_tenants'      => $tenants->where('is_demo', true)->count(),
            'total_users'       => User::whereNotNull('tenant_id')->count(),
            'total_mrr'         => (float) $tenants->where('status', 'Active')->sum('monthly_revenue'),
            'total_branches'    => Branch::count(),
            'plan_breakdown'    => [
                'Basic'      => $tenants->where('plan', 'Basic')->count(),
                'Pro'        => $tenants->where('plan', 'Pro')->count(),
                'Enterprise' => $tenants->where('plan', 'Enterprise')->count(),
            ],
        ];

        return Inertia::render('super-admin/Dashboard', [
            'tenants' => $mappedTenants,
            'stats'   => $stats,
        ]);
    }
}
