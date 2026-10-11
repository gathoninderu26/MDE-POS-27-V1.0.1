<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicPortalController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\SuperAdmin\TenantDetailController;
use App\Http\Controllers\SuperAdmin\TenantUserController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\IdentifyTenantFromSubdomain;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─── Secret Stealth Super Admin Authentication ─────────────────────────────────
// Configured path (e.g. /mde-control-portal) on the central domain.
// Completely hidden from public view; visiting /super-admin directly returns a 404.

$secretAdminPath = config('app.super_admin_path', 'mde-control-portal');

// Dedicated Super Admin Authentication Gateways (both direct /super-admin/login and stealth route)
Route::get('super-admin/login', [SuperAdminAuthController::class, 'create'])
    ->name('super-admin.login.direct');
Route::post('super-admin/login', [SuperAdminAuthController::class, 'store'])
    ->name('super-admin.login.direct.store');

Route::get($secretAdminPath, [SuperAdminAuthController::class, 'create'])
    ->name('super-admin.login');
Route::post($secretAdminPath, [SuperAdminAuthController::class, 'store'])
    ->name('super-admin.login.store');
Route::post('super-admin/logout', [SuperAdminAuthController::class, 'destroy'])
    ->name('super-admin.logout');

// ─── Public Central Portal Routes ─────────────────────────────────────────────

Route::middleware([])->group(function () {
    Route::get('/', function (\Illuminate\Http\Request $request) {
        // If request is on a tenant subdomain or has an active tenant, route straight to dashboard (if authenticated) or dedicated login
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
            if ($request->user()) {
                return redirect()->route('dashboard');
            }
            return redirect()->to($tenant->getSubdomainUrl('/login'));
        }

        $tenants = \App\Models\Tenant::where('status', '!=', 'Suspended')
            ->select(['id', 'name', 'slug', 'business_type', 'plan', 'status', 'email', 'phone', 'address'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Welcome', [
            'tenants'    => $tenants,
            'baseDomain' => config('app.domain', 'localhost'),
        ]);
    })->name('home');

    Route::get('/welcome', function (\Illuminate\Http\Request $request) {
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
            if ($request->user()) {
                return redirect()->route('dashboard');
            }
            return redirect()->to($tenant->getSubdomainUrl('/login'));
        }
        return redirect('/');
    })->name('welcome');

    // Early Access Waitlist & Public Setup Inquiries
    Route::post('/waitlist', [PublicPortalController::class, 'storeWaitlist'])->name('waitlist.store');
    Route::post('/admin-inquiry', [PublicPortalController::class, 'storeAdminInquiry'])->name('admin-inquiry.store');
});

// ─── Super Admin Console ──────────────────────────────────────────────────────
// Protected by: auth + verified + super_admin middleware (aborts 404 if unauthorized)

Route::middleware(['auth', 'verified', 'super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {

        Route::get('/', [SuperAdminDashboard::class, 'index'])
            ->name('dashboard');

        // ── Tenants CRUD ──────────────────────────────────────────────────────
        Route::get('/tenants', [TenantController::class, 'index'])
            ->name('tenants');
        Route::post('/tenants', [TenantController::class, 'store'])
            ->name('tenants.store');
        Route::patch('/tenants/{tenant}', [TenantController::class, 'update'])
            ->name('tenants.update');
        Route::patch('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])
            ->name('tenants.suspend');
        Route::patch('/tenants/{tenant}/activate', [TenantController::class, 'activate'])
            ->name('tenants.activate');
        Route::patch('/tenants/{tenant}/modules', [TenantController::class, 'toggleModule'])
            ->name('tenants.modules.toggle');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])
            ->name('tenants.destroy');
        Route::post('/tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])
            ->name('tenants.impersonate');

        // ── Tenant Full Detail (all modules in one page) ──────────────────────
        Route::get('/tenants/{tenant}/detail', [TenantDetailController::class, 'show'])
            ->name('tenants.detail');
        Route::patch('/tenants/{tenant}/detail', [TenantDetailController::class, 'update'])
            ->name('tenants.detail.update');
        Route::post('/tenants/{tenant}/detail/users', [TenantDetailController::class, 'storeUser'])
            ->name('tenants.detail.users.store');
        Route::patch('/tenants/{tenant}/detail/users/{user}', [TenantDetailController::class, 'updateUser'])
            ->name('tenants.detail.users.update');
        Route::post('/tenants/{tenant}/detail/users/{user}/reset-password', [TenantDetailController::class, 'resetUserPassword'])
            ->name('tenants.detail.users.reset-password');
        Route::delete('/tenants/{tenant}/detail/users/{user}', [TenantDetailController::class, 'destroyUser'])
            ->name('tenants.detail.users.destroy');

        // ── Tenant User Management (legacy list view) ─────────────────────────
        Route::get('/tenants/{tenant}/users', [TenantUserController::class, 'index'])
            ->name('tenants.users');
        Route::post('/tenants/{tenant}/users', [TenantUserController::class, 'store'])
            ->name('tenants.users.store');
        Route::patch('/tenants/{tenant}/users/{user}', [TenantUserController::class, 'update'])
            ->name('tenants.users.update');
        Route::post('/tenants/{tenant}/users/{user}/reset-password', [TenantUserController::class, 'resetPassword'])
            ->name('tenants.users.reset-password');
        Route::delete('/tenants/{tenant}/users/{user}', [TenantUserController::class, 'destroy'])
            ->name('tenants.users.destroy');

        // Super-admin management modules
        Route::get('/subscriptions', function () {
            return Inertia::render('super-admin/Subscriptions');
        })->name('subscriptions');

        Route::get('/system-users', function () {
            return Inertia::render('super-admin/SystemUsers');
        })->name('system-users');

        Route::get('/modules', [TenantController::class, 'modules'])->name('modules');

        Route::get('/payment-methods', function () {
            return Inertia::render('super-admin/PaymentMethods');
        })->name('payment-methods');

        Route::get('/backups', function () {
            return Inertia::render('super-admin/Backups');
        })->name('backups');

        Route::get('/security-policies', function () {
            return Inertia::render('super-admin/SecurityPolicies');
        })->name('security-policies');
    });

Route::post('/super-admin/stop-impersonating', [TenantController::class, 'stopImpersonating'])
    ->name('super-admin.stop-impersonating')
    ->middleware('auth');

// ─── Tenant Application Routes ────────────────────────────────────────────────
// Applies Subdomain Tenant Resolver Middleware & Tenant User Protection

Route::middleware(['auth', 'verified', 'tenant_user'])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // POS
        Route::middleware('module:pos')->group(function () {
            Route::get('pos', [SaleController::class, 'pos'])->name('pos');
            Route::post('pos/sale', [SaleController::class, 'store'])->name('pos.sale');
        });

        // Sales & Orders
        Route::middleware('module:sales')->group(function () {
            Route::get('sales', [SaleController::class, 'index'])->name('sales');
            Route::get('sales/returns', function () {
                return Inertia::render('sales/Returns');
            })->name('sales.returns');
        });

        // Products Catalog
        Route::middleware('module:products')->group(function () {
            Route::get('products/barcode-lookup', [ProductController::class, 'barcodeLookup'])->name('products.barcode-lookup');
            Route::get('products/categories', [ProductCategoryController::class, 'index'])->name('products.categories');
            Route::post('products/categories', [ProductCategoryController::class, 'store'])->name('products.categories.store');
            Route::post('products/categories/import-orphaned', [ProductCategoryController::class, 'importOrphaned'])->name('products.categories.import-orphaned');
            Route::patch('products/categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('products.categories.update');
            Route::delete('products/categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('products.categories.destroy');

            Route::get('products', [ProductController::class, 'index'])->name('products');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        });

        // Inventory Management
        Route::middleware('module:inventory')->group(function () {
            Route::get('inventory', [ProductController::class, 'inventory'])->name('inventory');
            Route::post('inventory/adjust', [ProductController::class, 'adjustStock'])->name('inventory.adjust');
            Route::post('inventory/transfer', [ProductController::class, 'transferStock'])->name('inventory.transfer');
        });

        // Branch Outlets
        Route::middleware('module:branches')->group(function () {
            Route::get('branches', [BranchController::class, 'index'])->name('branches');
            Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
            Route::patch('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
        });

        // Staff / Users
        Route::middleware('module:users')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::post('users/import', [UserController::class, 'import'])->name('users.import');
            Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::patch('users/{user}/archive', [UserController::class, 'archive'])->name('users.archive');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        // Expenses & Petty Cash
        Route::middleware('module:expenses')->group(function () {
            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses');
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        });

        // Purchases & Supplier Orders
        Route::middleware('module:purchases')->group(function () {
            Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases');
            Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
            Route::patch('purchases/{purchase}/receive', [PurchaseController::class, 'receiveStock'])->name('purchases.receive');
            Route::post('purchases/{purchase}/invoice/upload', [PurchaseController::class, 'uploadInvoice'])->name('purchases.invoice.upload');
            Route::post('purchases/{purchase}/invoice/reconcile', [PurchaseController::class, 'reconcileInvoice'])->name('purchases.invoice.reconcile');
            Route::get('purchases/{purchase}/invoice/document', [PurchaseController::class, 'viewInvoiceDocument'])->name('purchases.invoice.document');
            Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
        });

        // Suppliers
        Route::middleware('module:suppliers,purchases')->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::post('suppliers/{supplier}/payment', [SupplierController::class, 'recordPayment'])->name('suppliers.payment');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        });

        // Customers
        Route::middleware('module:customers')->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('customers');
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::post('customers/{customer}/payment', [CustomerController::class, 'recordPayment'])->name('customers.payment');
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        });

        // Reports & Analytics
        Route::middleware('module:reports')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports');
        });

        // Store & System Settings
        Route::middleware('module:settings')->group(function () {
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

require __DIR__.'/settings.php';

// Auth routes with subdomain tenant resolution
Route::middleware([])->group(function () {
    require __DIR__.'/auth.php';
});

// ─── M-Pesa Daraja API Routes ─────────────────────────────────────────────────
//
// /mpesa/callback  → public, CSRF-exempt (Safaricom posts here)
// /mpesa/stk-push  → auth-protected, sends STK prompt to customer phone
// /mpesa/stk-query → auth-protected, polls for payment confirmation

// Callback must be CSRF-exempt – use withoutMiddleware to exclude it
Route::post('/mpesa/callback', [MpesaController::class, 'callback'])
    ->name('mpesa.callback')
    ->withoutMiddleware(['web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth'])->group(function () {
    Route::post('/mpesa/stk-push',  [MpesaController::class, 'stkPush'])->name('mpesa.stk-push');
    Route::post('/mpesa/stk-query', [MpesaController::class, 'stkQuery'])->name('mpesa.stk-query');
});

// ─── Cloud Sync API (Local MySQL <-> Supabase) ───────────────────────────────
Route::middleware(['auth'])->prefix('api/sync')->name('sync.')->group(function () {
    Route::get('/status', function () {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : request()->user()?->tenant;
        return response()->json(app(\App\Services\CloudSyncService::class)->getSyncStatus($tenant?->id));
    })->name('status');

    Route::post('/trigger', function () {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : request()->user()?->tenant;
        $res = app(\App\Services\CloudSyncService::class)->pushPendingToCloud($tenant?->id);
        return response()->json($res);
    })->name('trigger');
});
