<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::withCount('users')->latest()->get();

        $stats = [
            'total_tenants'     => Tenant::count(),
            'active_tenants'    => Tenant::where('status', 'Active')->count(),
            'suspended_tenants' => Tenant::where('status', 'Suspended')->count(),
            'trial_tenants'     => Tenant::where('status', 'Trial')->count(),
            'total_users'       => User::whereNotNull('tenant_id')->count(),
            'total_mrr'         => Tenant::where('status', 'Active')->sum('monthly_revenue'),
        ];

        return Inertia::render('super-admin/Dashboard', [
            'tenants' => $tenants,
            'stats'   => $stats,
        ]);
    }
}
