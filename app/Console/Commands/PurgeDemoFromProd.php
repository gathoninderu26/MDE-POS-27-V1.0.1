<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time internal command: removes demo tenants (is_demo=true) and their
 * associated users/branches from the PRODUCTION database.sqlite.
 *
 * Run ONCE after demo:reset has successfully populated demo.sqlite.
 * Safe to run multiple times (idempotent — nothing to delete on second run).
 */
class PurgeDemoFromProd extends Command
{
    protected $signature   = 'demo:purge-from-prod {--force : Skip confirmation}';
    protected $description = 'Remove demo tenants and their data from the production database (one-time migration).';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            '⚠️  This will permanently DELETE demo tenants from the PRODUCTION database. Continue?',
            false
        )) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        $slugs = ['nairobi-fresh-mart', 'nairobi-fresh-clean', 'metro-wholesale-retail', 'metro-wholesale-clean'];

        // Work explicitly on the default connection
        $prodConn = config('database.default');

        $tenantIds = DB::connection($prodConn)
            ->table('tenants')
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->toArray();

        $this->info('Demo tenant IDs in production DB: ' . implode(', ', $tenantIds) ?: '(none)');

        if (empty($tenantIds)) {
            $this->info('✅ Nothing to remove — production DB is already demo-free.');
            return self::SUCCESS;
        }

        // Order matters for FK constraints
        $tables = [
            'sale_items',
            'sales',
            'products',
            'product_categories',
            'customers',
            'suppliers',
            'purchases',
            'expenses',
            'branches',
            'users',
        ];

        foreach ($tables as $table) {
            $deleted = DB::connection($prodConn)
                ->table($table)
                ->whereIn('tenant_id', $tenantIds)
                ->delete();
            if ($deleted > 0) {
                $this->line("  Deleted {$deleted} rows from [{$table}]");
            }
        }

        $removed = DB::connection($prodConn)
            ->table('tenants')
            ->whereIn('slug', $slugs)
            ->delete();

        $this->info("  Deleted {$removed} demo tenants from [tenants]");

        $remaining = DB::connection($prodConn)->table('tenants')->count();
        $this->info('');
        $this->info("✅ Production DB purged. Remaining tenants: {$remaining}");

        $rows = DB::connection($prodConn)->table('tenants')->select(['id', 'slug', 'name'])->get();
        foreach ($rows as $row) {
            $this->line("  [{$row->id}] {$row->slug} — {$row->name}");
        }

        return self::SUCCESS;
    }
}
