<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ClockSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $branches = Branch::where('tenant_id', $tenantId)->get(['id', 'name', 'code', 'location']);

        $users = User::where('tenant_id', $tenantId)
            ->get()
            ->map(function ($u) {
                $salesCount = $u->sales()->count();
                $salesTotal = (float) $u->sales()->sum('total');
                $salesProfit = (float) $u->sales()->sum('profit');
                $avgTicket = $salesCount > 0 ? round($salesTotal / $salesCount, 2) : 0;

                $recentSales = $u->sales()->latest()->take(30)->get()->map(fn($s) => [
                    'id' => $s->id,
                    'receipt_number' => $s->receipt_number,
                    'total' => (float) $s->total,
                    'profit' => (float) $s->profit,
                    'payment_method' => $s->payment_method,
                    'payment_status' => $s->payment_status,
                    'customer_name' => $s->customer_name ?: 'Walk-in Customer',
                    'created_at' => $s->created_at?->format('d M Y, H:i'),
                    'sale_date' => $s->created_at?->toDateString(),
                    'shift_name' => $s->created_at && $s->created_at->isToday()
                        ? 'Today (' . $s->created_at->format('d M') . ') - Current Shift'
                        : ($s->created_at ? $s->created_at->format('d M Y') . ' - Shift Archive' : 'Previous Shift'),
                ]);

                $clockSessions = $u->clockSessions()->with('branch')->latest()->take(8)->get()->map(fn($cs) => [
                    'id' => $cs->id,
                    'branch_name' => $cs->branch?->name ?? 'Main Branch',
                    'clocked_in_at' => $cs->clocked_in_at?->format('d M Y, H:i'),
                    'clocked_out_at' => $cs->clocked_out_at ? $cs->clocked_out_at->format('d M Y, H:i') : null,
                    'duration' => $cs->clocked_out_at && $cs->clocked_in_at
                        ? round($cs->clocked_in_at->diffInMinutes($cs->clocked_out_at) / 60, 1) . ' hrs'
                        : 'Active Shift',
                    'status' => $cs->status,
                ]);

                $sessions = DB::table('sessions')
                    ->where('user_id', $u->id)
                    ->orderBy('last_activity', 'desc')
                    ->take(5)
                    ->get()
                    ->map(fn($s) => [
                        'id' => $s->id,
                        'ip_address' => $s->ip_address ?? '127.0.0.1',
                        'user_agent' => $s->user_agent,
                        'last_active' => date('d M Y, H:i', $s->last_activity),
                    ]);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'phone' => $u->phone,
                    'status' => $u->status ?? 'Active',
                    'created_at' => $u->created_at?->toDateString(),
                    'created_at_formatted' => $u->created_at?->format('d M Y'),
                    'sales_count' => $salesCount,
                    'sales_total' => $salesTotal,
                    'sales_profit' => $salesProfit,
                    'avg_ticket' => $avgTicket,
                    'recent_sales' => $recentSales,
                    'clock_sessions' => $clockSessions,
                    'sessions' => $sessions,
                ];
            });

        return Inertia::render('users/Index', [
            'users' => $users,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|string',
            'phone'    => 'nullable|string|max:50',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'role'              => $validated['role'],
            'phone'             => $validated['phone'] ?? null,
            'tenant_id'         => $tenant->id,
            'status'            => 'Active',
            'is_super_admin'    => false,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', "Staff member \"{$validated['name']}\" added.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($user->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,'.$user->id,
            'role'     => 'sometimes|string',
            'phone'    => 'nullable|string|max:50',
            'status'   => 'sometimes|string|in:Active,Suspended,Archived',
            'password' => 'nullable|string|min:8',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', "User \"{$user->name}\" updated successfully.");
    }

    /**
     * Toggle a user's status between Active ↔ Suspended.
     */
    public function suspend(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($user->tenant_id === $tenant->id, 403);

        if ($user->role === 'Owner') {
            return back()->withErrors(['error' => 'Cannot suspend the business owner account.']);
        }

        $user->status = ($user->status === 'Suspended') ? 'Active' : 'Suspended';
        $user->save();

        $action = $user->status === 'Suspended' ? 'suspended' : 'reactivated';
        return back()->with('success', "User \"{$user->name}\" {$action}.");
    }

    /**
     * Soft-archive a user (marks as Archived without hard deletion).
     */
    public function archive(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($user->tenant_id === $tenant->id, 403);

        if ($user->role === 'Owner') {
            return back()->withErrors(['error' => 'Cannot archive the business owner account.']);
        }

        $user->update(['status' => 'Archived']);

        return back()->with('success', "User \"{$user->name}\" archived.");
    }

    /**
     * Hard delete a user (permanent, only for non-Owner accounts).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($user->tenant_id === $tenant->id, 403);

        if ($user->role === 'Owner') {
            return back()->withErrors(['error' => 'Cannot delete business owner account.']);
        }

        $user->delete();

        return back()->with('success', 'User removed.');
    }
}
