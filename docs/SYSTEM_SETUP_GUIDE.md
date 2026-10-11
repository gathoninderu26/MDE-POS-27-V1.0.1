# MDE-POS Multi-Tenant SaaS Platform — Full System Setup Guide

> **Version:** 1.0.1
> **Stack:** Laravel 11 · Inertia.js · Vue 3 · Vite · MySQL/SQLite
> **Last Updated:** October 2026

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Environment Configuration](#2-environment-configuration)
3. [Database Architecture](#3-database-architecture)
4. [Tenant Resolution System](#4-tenant-resolution-system)
5. [Super Admin Console](#5-super-admin-console)
6. [Tenant Provisioning Flow](#6-tenant-provisioning-flow)
7. [Subdomain & URL Routing](#7-subdomain--url-routing)
8. [Authentication & Tenant Isolation](#8-authentication--tenant-isolation)
9. [Module System](#9-module-system)
10. [Role & Permission Matrix](#10-role--permission-matrix)
11. [Payment Methods Configuration](#11-payment-methods-configuration)
12. [Frontend Tenant Context](#12-frontend-tenant-context)
13. [Demo Tenant Sandboxing](#13-demo-tenant-sandboxing)
14. [Cloud Sync & M-Pesa Integration](#14-cloud-sync--m-pesa-integration)
15. [Local Development Setup (XAMPP)](#15-local-development-setup-xampp)
16. [Production Deployment Checklist](#16-production-deployment-checklist)
17. [Tenant Account Post-Provisioning Steps](#17-tenant-account-post-provisioning-steps)
18. [Security Policies](#18-security-policies)
19. [Key File Reference](#19-key-file-reference)

---

## 1. Architecture Overview

MDE-POS is a **multi-tenant SaaS Point-of-Sale platform** where a single application instance serves multiple independent businesses (tenants). Each tenant is fully isolated from others — they have their own users, products, sales, and settings — but share the same Laravel application and MySQL database using `tenant_id` column-level row scoping.

### Deployment Modes

| Mode | Description | Use Case |
|------|-------------|----------|
| `multi` | Default SaaS mode — multiple tenants on one server | Cloud / VPS deployment |
| `single` | Locked to one tenant permanently | Dedicated on-premise POS PC (XAMPP) |

### System Actors

```
+-----------------------------------------------------------------+
|                      CENTRAL DOMAIN                             |
|                   (e.g. mdepos.co.ke)                           |
|                                                                  |
|   +----------------+          +------------------------------+  |
|   |  Welcome Page  |          |   Super Admin Console        |  |
|   |  (tenant list) |          |   /mde-control-portal        |  |
|   +----------------+          +------------------------------+  |
+-----------------------------------------------------------------+
         |                              |
         | Tenant Subdomain             | Creates Tenants
         v                              v
+----------------------+     +----------------------+
|  store.mdepos.co.ke  |     |  mega.mdepos.co.ke   |
|  Tenant Login Portal |     |  Tenant Login Portal |
|  -> Dashboard / POS  |     |  -> Dashboard / POS  |
+----------------------+     +----------------------+
```

---

## 2. Environment Configuration

### Key `.env` Variables

```ini
# Application
APP_NAME="MDE POS"
APP_ENV=production
APP_KEY=base64:...          # php artisan key:generate
APP_DEBUG=false
APP_URL=https://mdepos.co.ke
APP_DOMAIN=mdepos.co.ke     # Base domain for subdomain routing

# Super Admin
SUPER_ADMIN_PATH=mde-control-portal    # Secret stealth path (not /super-admin)

# Database (Central MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mde_pos_central
DB_USERNAME=root
DB_PASSWORD=your_password

# Demo Sandbox (SQLite per tenant)
# No config needed for SQLite - auto-set to database/demo.sqlite
# After setup run: php artisan demo:reset --force

# Session / Cache
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Single-Tenant Dedicated Machine (only for on-premise XAMPP machines)
# TENANT_MODE=single
# LOCKED_TENANT_ID=3
# LOCKED_TENANT_SLUG=my-store
```

### Critical: `APP_DOMAIN`

`APP_DOMAIN` is the single most important environment variable for subdomain routing. It tells the middleware where to strip subdomain prefixes:

- **Local dev:** `APP_DOMAIN=localhost` -> uses `?tenant=slug` query parameters
- **Production:** `APP_DOMAIN=mdepos.co.ke` -> uses `store.mdepos.co.ke` subdomains

---

## 3. Database Architecture

### Central Database Tables (shared, `tenant_id` scoped)

| Table | Purpose |
|-------|---------|
| `tenants` | All registered business accounts |
| `users` | All staff users (`tenant_id` FK links to their employer) |
| `branches` | Physical store locations per tenant |
| `products` | Product catalog per tenant |
| `product_categories` | Category taxonomy per tenant |
| `sales` | POS transactions |
| `sale_items` | Line items for each sale |
| `purchases` | Supplier purchase orders |
| `expenses` | Petty cash and expense records |
| `customers` | Customer accounts (credit, loyalty) |
| `suppliers` | Supplier contact and payment records |
| `sync_outbox` | Queue of records to push to Supabase cloud |
| `sessions` | Laravel session storage |
| `jobs` | Laravel queue jobs |

### Demo Database (SQLite)

Demo tenants (`is_demo = true`) use per-tenant SQLite files stored at `database/demo-{slug}.sqlite`. The `DemoDatabaseService` switches the Eloquent default connection to the correct file when a demo tenant is identified. This ensures demo data never contaminates real tenant data.

---

## 4. Tenant Resolution System

The core of the multi-tenancy system is the `IdentifyTenantFromSubdomain` middleware, registered globally in `bootstrap/app.php`.

### Resolution Order

```
Incoming Request
      |
      v
+-----------------------------------------------------+
|  1. SINGLE MODE CHECK                               |
|     TENANT_MODE=single? -> bind locked tenant       |
|     -> validate user belongs to locked tenant       |
+----------------------+------------------------------+
                       | (if not single mode)
                       v
+-----------------------------------------------------+
|  2. HOST CHECK                                      |
|     host === baseDomain or 127.0.0.1?               |
|     -> Central domain path                          |
|          |- ?tenant=slug param? -> resolve tenant   |
|          |- Authenticated user has tenant? -> bind  |
|          \- Session has active_tenant_slug? -> bind |
+----------------------+------------------------------+
                       | (if on subdomain)
                       v
+-----------------------------------------------------+
|  3. SUBDOMAIN PARSE                                 |
|     Extract leftmost subdomain label                |
|     -> Lookup Tenant::where('slug', $subdomain)     |
|     -> 404 if not found, 403 if suspended           |
|     -> Bind to service container: app('currentTenant')|
+-----------------------------------------------------+
```

### Service Container Binding

Once resolved, the tenant is available globally:

```php
// In any controller, middleware, or service:
$tenant = app('currentTenant');

// Check if a tenant is bound:
if (app()->bound('currentTenant')) { ... }
```

---

## 5. Super Admin Console

### Access URL

The Super Admin login is intentionally hidden at a secret path configured in `.env`:

```
SUPER_ADMIN_PATH=mde-control-portal
```

- Visiting `/mde-control-portal` -> Super Admin login form
- Visiting `/super-admin/login` -> Also works (direct alias)
- Visiting `/super-admin` directly -> Returns 404 (security measure)

### Super Admin Capabilities

| Feature | Route | Description |
|---------|-------|-------------|
| Dashboard | `/super-admin/` | Overview statistics |
| Tenant List | `/super-admin/tenants` | All businesses, status, metrics |
| Create Tenant | POST `/super-admin/tenants` | Full provisioning with credentials |
| Edit Tenant | PATCH `/super-admin/tenants/{id}` | Update profile / plan |
| Suspend | PATCH `/super-admin/tenants/{id}/suspend` | Blocks all tenant logins |
| Activate | PATCH `/super-admin/tenants/{id}/activate` | Restores access |
| Delete | DELETE `/super-admin/tenants/{id}` | Permanently removes tenant + users |
| Module Toggle | PATCH `/super-admin/tenants/{id}/modules` | Enable/disable modules |
| Impersonate | POST `/super-admin/tenants/{id}/impersonate` | Log in as the tenant Owner |
| Stop Impersonate | POST `/super-admin/stop-impersonating` | Return to Super Admin session |
| Tenant Detail | GET `/super-admin/tenants/{id}/detail` | Full-page tenant management |

### Super Admin Guard

All super admin routes are protected by three stacked middleware:
1. `auth` — must be logged in
2. `verified` — email must be verified
3. `super_admin` — `is_super_admin` column must be `true`

Requests that pass `auth` + `verified` but fail `super_admin` receive a **404**, not a 403, to hide the console existence.

---

## 6. Tenant Provisioning Flow

When the Super Admin creates a new tenant, `TenantProvisioningService::provision()` executes the following in a **single atomic database transaction**:

```
Step 1: Resolve enabled modules
        (based on business_type + plan + branch_count)

Step 2: Generate unique slug
        (from business name, appending -1, -2 if collision)

Step 3: Calculate storage quota
        Basic -> 1 GB | Pro -> 5 GB | Enterprise -> 50 GB

Step 4: Build device & network security policies

Step 5: Resolve payment methods configuration
        (Cash, M-Pesa, Card, Bank, Credit)

Step 6: CREATE tenants record
        (status=Active, is_demo=false, enabled_modules=[...])

Step 7: CREATE owner User account
        (role=Owner, email_verified_at=now())
        Password: provided or auto-generated as "Owner@XXXX!"

Step 8: CREATE default HQ Branch
        (code=HQ-01, type=Main HQ, is_main=true)

Step 9: SEED starter product categories
        (5 categories preset for the business type)

Step 10: Return credentials object
         -> tenant_id, tenant_name, subdomain, subdomain_url,
            owner_email, owner_password
```

### Credentials Flash After Provisioning

After provisioning, the Super Admin UI displays a **one-time credentials banner** containing:
- Business Name
- Tenant Slug (subdomain identifier)
- **Login URL** (the exact URL the tenant owner must use to log in)
- Owner Email
- Owner Password (shown only once — not stored in plain text after this point)

> **Important:** The Super Admin must copy and securely deliver these credentials to the tenant. The password is hashed immediately — there is no way to retrieve it later except through a password reset.

---

## 7. Subdomain & URL Routing

### How Subdomains Map to Tenants

Each tenant's `slug` becomes their subdomain identifier. The `slug` is auto-generated from the business name:

| Business Name | Slug | Production URL | Local Dev URL |
|---------------|------|----------------|---------------|
| Nairobi Fresh Mart | `nairobi-fresh-mart` | `https://nairobi-fresh-mart.mdepos.co.ke` | `http://localhost:8000/login?tenant=nairobi-fresh-mart` |
| Mega Superstore | `mega-superstore` | `https://mega-superstore.mdepos.co.ke` | `http://localhost:8000/login?tenant=mega-superstore` |

### URL Generation Logic (`Tenant::getSubdomainUrl()`)

```php
public function getSubdomainUrl(string $path = ''): string
{
    $baseDomain = config('app.domain', 'localhost');

    // Local development -> use ?tenant= query parameter
    if ($baseDomain === 'localhost' || str_contains($baseDomain, 'localhost')) {
        return "{$scheme}://{$baseDomain}{$portSuffix}{$prefix}?tenant={$this->slug}";
    }

    // Production -> real subdomain
    return "{$scheme}://{$this->slug}.{$baseDomain}{$portSuffix}{$pathSegment}";
}
```

### DNS / Server Configuration (Production)

For subdomain routing to work in production:

1. **DNS Wildcard Record:**
   Add `*.mdepos.co.ke -> [your server IP]` as an A record in your DNS panel.

2. **Nginx Wildcard VHost:**
   ```nginx
   server {
       server_name *.mdepos.co.ke;
       root /var/www/mde-pos/public;
       index index.php;
       # ... standard Laravel Nginx config
   }
   ```

3. **SSL Wildcard Certificate:**
   Use Let's Encrypt with Certbot and DNS challenge for `*.mdepos.co.ke`.

---

## 8. Authentication & Tenant Isolation

### Tenant Login Journey

When a tenant user visits their subdomain URL:

```
1. User navigates to: https://store.mdepos.co.ke/login
   (or local: http://localhost:8000/login?tenant=store)

2. IdentifyTenantFromSubdomain middleware:
   -> Resolves "store" tenant from the database
   -> Binds tenant to app('currentTenant')
   -> Saves to session: active_tenant_slug = "store"

3. Login page (resources/js/pages/auth/Login.vue):
   -> Reads tenant from Inertia shared props
   -> Displays tenant's Business Name prominently
   -> Shows the subdomain identifier badge
   -> Hides all "back to home" navigation links

4. On successful login:
   -> Laravel verifies email + password
   -> Checks user.tenant_id matches the resolved tenant
   -> Redirects to /dashboard

5. All subsequent dashboard requests:
   -> Session carries active_tenant_slug
   -> Middleware re-binds currentTenant from session
   -> All data queries are automatically scoped to tenant_id
```

### Cross-Tenant & Super Admin Lockout Rules

| Scenario | Action |
|----------|--------|
| User from Tenant A tries to log in on Tenant B's subdomain | Logged out + error: "not authorized for [Tenant B]" |
| Super Admin visits a tenant subdomain | Redirected to `/super-admin` console |
| Tenant user visits `/` or `/welcome` on their subdomain | Redirected to their `/login` (unauthenticated) or `/dashboard` (authenticated) |
| Suspended tenant's users try to log in | Inertia `TenantNotFound` 403 page |
| Unknown subdomain accessed | Inertia `TenantNotFound` 404 page |

### Single-Tenant Machine Lock (XAMPP / POS PC)

For on-premise POS machines, set in `.env`:

```ini
TENANT_MODE=single
LOCKED_TENANT_ID=3        # Optional: find by ID
LOCKED_TENANT_SLUG=store  # Optional: find by slug
```

With `single` mode:
- The middleware always binds the locked tenant regardless of URL
- Any user whose `tenant_id` does NOT match the locked tenant is immediately logged out
- The machine is physically restricted to one business

---

## 9. Module System

Modules gate entire sections of the application. A tenant only sees routes and navigation items for their enabled modules.

### Available Modules

| Module Key | Description |
|------------|-------------|
| `pos` | Point-of-Sale terminal |
| `sales` | Sales history and order management |
| `products` | Product catalog management |
| `inventory` | Stock levels, adjustments, transfers |
| `purchases` | Supplier purchase orders and receiving |
| `customers` | Customer accounts and credit |
| `suppliers` | Supplier directory and payments |
| `expenses` | Petty cash and business expenses |
| `reports` | Analytics and financial reports |
| `users` | Staff user management |
| `branches` | Multi-branch outlet management |
| `settings` | Store configuration and preferences |
| `prescriptions` | (Pharmacy only) Rx prescription tracking |
| `tables` | (Restaurant only) Table management |
| `kitchen` | (Restaurant only) KDS kitchen display |
| `fuel` | (Petrol Station only) Fuel pump management |

### Module Gating (Backend)

Routes are wrapped with `middleware('module:key')`:

```php
Route::middleware('module:pos')->group(function () {
    Route::get('pos', [SaleController::class, 'pos'])->name('pos');
});
```

The `module` middleware checks `$tenant->hasModule($key)` and aborts 403 if disabled.

### Module Defaults by Business Type

| Business Type | Auto-Enabled Modules |
|---------------|----------------------|
| Supermarket | pos, sales, products, inventory, purchases, customers, suppliers, expenses, reports, users, settings |
| Pharmacy | + prescriptions |
| Restaurant | + tables, kitchen (no purchases/suppliers) |
| Petrol Station | + fuel |
| Bakery / General Trade | Lighter set (no purchases/suppliers by default) |

Enterprise plan and/or `branch_count > 1` automatically adds the `branches` module.

---

## 10. Role & Permission Matrix

Every tenant user has a `role` column. Permissions are resolved server-side in `HandleInertiaRequests` and passed to the frontend via Inertia shared props (`auth.permissions`).

| Permission | Owner | Admin | Manager | Cashier | Inventory Mgr | Accountant | Supervisor |
|------------|:-----:|:-----:|:-------:|:-------:|:-------------:|:----------:|:----------:|
| POS (full) | Yes | Yes | Yes | Create only | No | No | Yes |
| Sales (delete) | Yes | Yes | No | No | No | No | No |
| Products (delete) | Yes | Yes | No | No | No | No | No |
| Users (manage) | Yes | View/Edit | View only | No | No | No | No |
| Settings | Yes | View/Edit | No | No | No | No | No |
| Expenses (full) | Yes | Yes | View/Edit | No | No | Full | No |
| Reports | Yes | Yes | View only | No | View only | Full | View only |

---

## 11. Payment Methods Configuration

Payment channels are configured **per tenant** during provisioning and stored in `tenants.settings.payment_methods` as a JSON array.

### Supported Payment Methods

| Code | Type | Notes |
|------|------|-------|
| `cash` | Cash | Always enabled by default |
| `mpesa_till` | Mobile Money | M-Pesa Buy Goods (Lipa na M-Pesa Till Number) |
| `mpesa_paybill` | Mobile Money | M-Pesa Paybill with account number |
| `card` | Card | PDQ/POS card terminal (Visa/Mastercard) |
| `bank` | Bank | Bank wire or Pesalink transfer |
| `credit` | Credit | Customer credit account / invoice billing |

### M-Pesa STK Push (Daraja API)

The system supports real-time M-Pesa STK Push via Safaricom Daraja:

- `POST /mpesa/stk-push` — Sends payment prompt to customer's phone
- `POST /mpesa/stk-query` — Polls Daraja for payment status
- `POST /mpesa/callback` — CSRF-exempt webhook that Safaricom calls back

Configure Daraja credentials in `.env`:
```ini
MPESA_CONSUMER_KEY=...
MPESA_CONSUMER_SECRET=...
MPESA_SHORTCODE=...
MPESA_PASSKEY=...
MPESA_CALLBACK_URL=https://mdepos.co.ke/mpesa/callback
```

---

## 12. Frontend Tenant Context

### Inertia Shared Props (available on every page)

`HandleInertiaRequests::share()` injects the following into every Inertia page component:

```typescript
{
  name: string,              // App name
  isSubdomain: boolean,      // true if a tenant is currently resolved
  tenant: {                  // null on central domain without auth
    id: string,
    name: string,            // Includes "[DEMO]" prefix for demo tenants
    slug: string,
    subdomain_url: string,   // Full login URL for this tenant
    email, phone, address, kra_pin,
    business_type, plan, currency, status,
    branch_count, is_multi_branch,
    enabled_modules: string[],
    device_policy, network_policy,
    settings: { payment_methods: [...] }
  },
  auth: {
    user: { id, name, email, role, is_super_admin, ... },
    tenant: { ... },         // Same as above, via auth context
    permissions: { ... },    // Role-based permission matrix
    is_impersonating: boolean,
    sync: { mode, is_single_tenant, pending_outbox }
  },
  flash: { success, error, warning, info, createdCredentials },
  badgeCounts: { todaySales, totalProducts, lowStock, ... }
}
```

### `useTenant()` Composable

```typescript
import { useTenant } from '@/composables/useTenant';

const {
  currentTenant,        // Reactive tenant object (reads from Inertia props)
  isSubdomain,          // true when on a tenant-specific URL
  isDemo,               // true for demo sandboxed tenants
  hasModule,            // (key: string) => boolean
  paymentMethods,       // Full payment method list
  activePaymentMethods, // Only enabled payment methods
  getTenantSubdomainUrl,// (slug, path) => string — generates correct URL per env
  redirectToTenant,     // (slug, path) => void — navigates to tenant URL
} = useTenant();
```

### `isSubdomain` Logic in Layouts

All auth layouts (`AuthSimpleLayout.vue`, `AuthCardLayout.vue`, `AuthSplitLayout.vue`) and `Welcome.vue` check `isSubdomain` to:

- **Hide** the "Back to Home" / central navigation links when on a tenant subdomain
- **Display** the tenant's Business Name instead of the generic app name
- **Prevent** tenant users from navigating away to the central welcome page

---

## 13. Demo Tenant Sandboxing

Demo tenants allow prospective customers to explore the system with pre-seeded mock data, completely isolated from production data.

### Demo Tenant Identifiers

- `tenants.is_demo = true`
- Slug matches a known demo slug (e.g., `nairobi-fresh-mart`, `apex-care-pharmacy`)
- Name is prefixed with `[DEMO]` in the UI

### Database Isolation

Demo tenants run against a **per-tenant SQLite file**:

1. `IdentifyTenantFromSubdomain` detects `is_demo = true`
2. Calls `DemoDatabaseService::switchTo($tenant)`
3. Configures the `demo` connection to point at `database/demo-{slug}.sqlite`
4. Sets this as the default Eloquent connection for the request lifetime
5. On request termination (`terminate()`), resets back to `mysql`

### Reset Demo Data

```bash
php artisan demo:reset --force
```

This wipes and re-seeds all demo SQLite databases with fresh mock data.

---

## 14. Cloud Sync & M-Pesa Integration

### Cloud Sync (Local <-> Supabase)

For hybrid deployments (local XAMPP + cloud backup):

- `GET /api/sync/status` — Returns pending sync outbox count
- `POST /api/sync/trigger` — Pushes pending records to Supabase

The `CloudSyncService` manages the `sync_outbox` table. Records written locally are queued and pushed to a configured Supabase PostgreSQL instance for cloud backup.

Configure in `.env`:
```ini
SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_KEY=your_service_role_key
SUPABASE_DB_HOST=...
SUPABASE_DB_NAME=...
```

See `docs/LOCAL_XAMPP_SUPABASE_SYNC_GUIDE.md` for full setup instructions.

---

## 15. Local Development Setup (XAMPP)

### Initial Setup

```bash
# 1. Clone the repository
git clone https://github.com/your-org/MDE-POS-27-V1.0.1.git
cd MDE-POS-27-V1.0.1

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Configure environment
cp .env.example .env
php artisan key:generate

# 5. Configure .env for local MySQL
#    DB_CONNECTION=mysql
#    DB_DATABASE=mde_pos
#    DB_USERNAME=root
#    DB_PASSWORD=

# 6. Run migrations
php artisan migrate

# 7. Seed demo data (optional)
php artisan demo:reset --force

# 8. Build frontend assets (production build)
npm run build
# OR for hot-reload dev:
npm run dev

# 9. Start Laravel server
php artisan serve
```

### Create First Super Admin

```bash
php artisan tinker
```

Then in Tinker:

```php
\App\Models\User::create([
    'name'             => 'Super Administrator',
    'email'            => 'admin@mdepos.co.ke',
    'password'         => \Illuminate\Support\Facades\Hash::make('your_secure_password'),
    'is_super_admin'   => true,
    'email_verified_at'=> now(),
]);
```

### Accessing in Local Dev

| URL | Description |
|-----|-------------|
| `http://localhost:8000` | Central welcome / tenant list |
| `http://localhost:8000/mde-control-portal` | Super Admin login |
| `http://localhost:8000/login?tenant=store-slug` | Tenant login portal |
| `http://localhost:8000/dashboard?tenant=store-slug` | Tenant dashboard |

---

## 16. Production Deployment Checklist

### Server Requirements

- PHP 8.2+
- MySQL 8.0+ (or MariaDB 10.6+)
- Nginx or Apache with URL rewriting
- Composer 2.x
- Node.js 20+ (for build only)
- SSL certificate (Wildcard recommended)

### Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Install / update PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Build frontend assets
npm ci && npm run build

# 4. Run database migrations
php artisan migrate --force

# 5. Clear & warm caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Set correct file permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Production Environment Settings

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mdepos.co.ke
APP_DOMAIN=mdepos.co.ke
SESSION_DOMAIN=.mdepos.co.ke   # Leading dot allows subdomain sessions
SUPER_ADMIN_PATH=your-secret-path
```

> **Critical:** Set `SESSION_DOMAIN=.mdepos.co.ke` (with leading dot) so that sessions set on the central domain are readable on all subdomains.

---

## 17. Tenant Account Post-Provisioning Steps

After the Super Admin creates a tenant account, the following must be communicated to the **tenant owner**:

### What the Tenant Receives

1. **Login URL** — Their unique subdomain URL:
   - Production: `https://their-slug.mdepos.co.ke/login`
   - Local dev: `http://localhost:8000/login?tenant=their-slug`

2. **Owner Email** — Their admin account email address

3. **Temporary Password** — Auto-generated as `Owner@XXXX!` or explicitly set

### First Login Instructions for Tenant Owner

1. Open the provided Login URL in a browser
2. Enter the provided Owner Email and Password
3. The login page will display their **Business Name** prominently — verify it matches
4. Upon successful login, change the password immediately in **Settings -> Profile**

### What is Pre-Configured After Provisioning

- One Owner user account (role: `Owner`)
- One default HQ branch (`code: HQ-01`)
- 5 starter product categories for their industry vertical
- Default payment methods (Cash + M-Pesa Till enabled)
- Module set matching their business type and plan
- Device and network security policies

### Next Steps for the Tenant Owner

1. **Change Password** — Settings -> Profile
2. **Add Staff Users** — Users section -> Invite staff with appropriate roles
3. **Configure Payment Methods** — Settings -> Payment to update M-Pesa till numbers
4. **Import or Add Products** — Products -> Import CSV or add individually
5. **Configure Branches** (if multi-branch) — Branches -> Add outlet locations

---

## 18. Security Policies

### Device Policy (stored in `tenants.device_policy`)

| Field | Description |
|-------|-------------|
| `selected_device_types` | Allowed device types (POS Terminal, Desktop PC, Tablet, etc.) |
| `require_device_binding` | Lock access to registered MAC/device fingerprints |
| `pos_hardware_count` | Max number of simultaneous POS terminals |
| `single_session_per_cashier` | Prevent same cashier from being logged in on 2 devices |

### Network Policy (stored in `tenants.network_policy`)

| Field | Description |
|-------|-------------|
| `block_vpn` | Detect and block VPN connections |
| `require_store_wifi` | Only allow access from registered SSID |
| `primary_store_wifi_ssid` | The expected store WiFi name |
| `block_hotspot` | Block mobile hotspot connections |
| `cellular_policy` | `allow_all` / `field_roles_only` / `block_cellular` |

### Cross-Tenant Security Guarantees

- A user's `tenant_id` is **always validated** against the resolved tenant on every request
- Attempting to access another tenant's subdomain with a valid session **forces logout**
- Super Admins visiting tenant subdomains are redirected to the super admin console — they cannot access tenant data through the normal tenant UI

---

## 19. Key File Reference

| File | Purpose |
|------|---------|
| `app/Http/Middleware/IdentifyTenantFromSubdomain.php` | Core tenant resolution + isolation middleware |
| `app/Http/Middleware/HandleInertiaRequests.php` | Injects tenant + auth + permissions into all Inertia pages |
| `app/Services/TenantProvisioningService.php` | Full tenant creation: DB records, owner user, branch, categories, payment methods |
| `app/Services/DemoDatabaseService.php` | Switches Eloquent to per-tenant SQLite for demo sessions |
| `app/Services/CloudSyncService.php` | Local -> Supabase cloud data sync |
| `app/Models/Tenant.php` | Tenant Eloquent model + `getSubdomainUrl()` helper |
| `app/Http/Controllers/SuperAdmin/TenantController.php` | CRUD + impersonation for Super Admin console |
| `routes/web.php` | All application routes, organized by role and middleware |
| `resources/js/composables/useTenant.ts` | Frontend tenant state, URL generation, module checks |
| `resources/js/pages/auth/Login.vue` | Tenant-branded login page |
| `resources/js/pages/Welcome.vue` | Central landing page with tenant directory |
| `resources/js/pages/super-admin/Tenants.vue` | Super Admin tenant management UI |
| `docs/TENANT_PAYMENT_METHODS_GUIDE.md` | Detailed payment channel configuration guide |
| `docs/LOCAL_XAMPP_SUPABASE_SYNC_GUIDE.md` | Hybrid local + cloud sync setup guide |

---

*This document covers the complete architecture of MDE-POS as of v1.0.1. For changes to any component, update the relevant section of this guide.*
