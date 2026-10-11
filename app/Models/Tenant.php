<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'kra_pin',
        'email',
        'phone',
        'country',
        'currency',
        'business_type',
        'is_demo',
        'plan',
        'billing_cycle',
        'status',
        'address',
        'branch_count',
        'user_count',
        'storage_used_mb',
        'storage_quota_mb',
        'monthly_revenue',
        'enabled_modules',
        'custom_modules',
        'device_policy',
        'network_policy',
        'settings',
        'next_billing_date',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'enabled_modules' => 'array',
            'custom_modules' => 'array',
            'device_policy' => 'array',
            'network_policy' => 'array',
            'settings' => 'array',
            'next_billing_date' => 'datetime',
            'monthly_revenue' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * All users that belong to this tenant.
     * IMPORTANT: Every user query for tenant data must be scoped through this.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function clockSessions(): HasMany
    {
        return $this->hasMany(ClockSession::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'Suspended');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'Suspended';
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    public function hasModule(string $module): bool
    {
        if ($this->enabled_modules === null) {
            return true;
        }

        return in_array($module, $this->enabled_modules);
    }

    /**
     * Determine if tenant operates as a multi-branch business.
     */
    public function isMultiBranch(): bool
    {
        return $this->hasModule('branches') && ($this->branch_count > 1 || $this->branches()->count() > 1);
    }

    /**
     * Storage utilization percentage (0–100).
     */
    public function storageUsagePercent(): float
    {
        if ($this->storage_quota_mb === 0) {
            return 0;
        }

        return round(($this->storage_used_mb / $this->storage_quota_mb) * 100, 1);
    }

    /**
     * Get the tenant's dedicated subdomain string.
     */
    public function getSubdomainAttribute(): string
    {
        return $this->slug;
    }

    /**
     * Generate full subdomain URL for this tenant.
     */
    public function getSubdomainUrl(string $path = ''): string
    {
        $scheme = request()->getScheme() ?: 'http';
        $port = request()->getPort();
        $portSuffix = (! $port || in_array($port, [80, 443])) ? '' : ":{$port}";
        $baseDomain = config('app.domain', 'localhost');

        $cleanPath = ltrim($path, '/');
        $pathSegment = $cleanPath ? "/{$cleanPath}" : '';

        // In local development (localhost / 127.0.0.1), use query parameter to ensure immediate browser accessibility
        if ($baseDomain === 'localhost' || $baseDomain === '127.0.0.1' || str_contains($baseDomain, 'localhost')) {
            $prefix = $pathSegment ?: '/login';
            $separator = str_contains($prefix, '?') ? '&' : '?';

            return "{$scheme}://{$baseDomain}{$portSuffix}{$prefix}{$separator}tenant={$this->slug}";
        }

        return "{$scheme}://{$this->slug}.{$baseDomain}{$portSuffix}{$pathSegment}";
    }
}
