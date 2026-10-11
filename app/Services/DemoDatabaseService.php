<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class DemoDatabaseService
{
    /**
     * Get the directory where demo SQLite databases are stored.
     */
    public static function getDemoDirectory(): string
    {
        return database_path('demo');
    }

    /**
     * Get the path to the master demo template database.
     */
    public static function getTemplatePath(): string
    {
        return database_path('demo/_template.sqlite');
    }

    /**
     * Get the path to a specific tenant's demo database.
     */
    public static function getTenantDatabasePath(string $slug): string
    {
        return database_path("demo/{$slug}.sqlite");
    }

    /**
     * When the resolved tenant is a demo, wire the 'demo' connection to the
     * tenant's isolated SQLite database and make it the default connection.
     *
     * Sessions, Cache, and Queue use explicit MySQL connection configurations
     * and remain unaffected.
     */
    public static function switchTo(Tenant $tenant): void
    {
        if (! $tenant->is_demo) {
            return;
        }

        $demoDir  = self::getDemoDirectory();
        $dbPath   = self::getTenantDatabasePath($tenant->slug);
        $template = self::getTemplatePath();

        if (! is_dir($demoDir)) {
            mkdir($demoDir, 0755, true);
        }

        if (! file_exists($dbPath) || filesize($dbPath) === 0) {
            if (file_exists($template) && filesize($template) > 0) {
                copy($template, $dbPath);
                self::adaptDatabaseForTenant($dbPath, $tenant);
            }
        }

        Config::set('database.connections.demo.database', $dbPath);
        DB::purge('demo');
        DB::setDefaultConnection('demo');
    }

    /**
     * Adapts a newly cloned SQLite template for the specific tenant:
     * - Matches tenant ID, name, slug, email, and business type
     * - Remaps foreign key references across all business tables
     * - Updates user emails to match the tenant slug
     * - If the tenant is a clean sandbox (*-clean), empties mock business data
     */
    public static function adaptDatabaseForTenant(string $dbPath, Tenant $tenant): void
    {
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. Update the tenant record
            $stmt = $pdo->prepare('UPDATE tenants SET id = ?, slug = ?, name = ?, email = ?, business_type = ?, plan = ? WHERE id = 1');
            $stmt->execute([
                $tenant->id,
                $tenant->slug,
                $tenant->name,
                $tenant->email ?? "demo@{$tenant->slug}.mdepos.co.ke",
                $tenant->business_type ?? 'Supermarket',
                $tenant->plan ?? 'Enterprise',
            ]);

            // 2. Remap tenant_id foreign keys from template's 1 to actual tenant ID
            $tables = [
                'branches',
                'products',
                'product_categories',
                'customers',
                'suppliers',
                'purchases',
                'expenses',
                'sales',
                'users',
            ];

            foreach ($tables as $table) {
                $stmtTable = $pdo->prepare("UPDATE {$table} SET tenant_id = ? WHERE tenant_id = 1");
                $stmtTable->execute([$tenant->id]);
            }

            // 3. Update staff user emails to match tenant slug
            $stmtEmail = $pdo->prepare("UPDATE users SET email = REPLACE(email, '@demo-store.mdepos.co.ke', '@' || ? || '.mdepos.co.ke')");
            $stmtEmail->execute([$tenant->slug]);

            // 4. If this is a clean sandbox (e.g. *-clean), purge mock transactional records
            if (str_ends_with($tenant->slug, '-clean')) {
                $cleanTables = ['sales', 'sale_items', 'purchases', 'expenses', 'products', 'customers', 'suppliers'];
                foreach ($cleanTables as $table) {
                    $pdo->exec("DELETE FROM {$table}");
                }
            }
        } catch (Throwable $e) {
            Log::warning("Failed to adapt demo SQLite database for {$tenant->slug}: " . $e->getMessage());
        }
    }

    /**
     * Reset a demo tenant's database back to the fresh master template.
     */
    public static function resetTenant(Tenant $tenant): bool
    {
        $dbPath   = self::getTenantDatabasePath($tenant->slug);
        $template = self::getTemplatePath();

        if (! file_exists($template)) {
            return false;
        }

        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        copy($template, $dbPath);
        self::adaptDatabaseForTenant($dbPath, $tenant);

        return true;
    }

    /**
     * Delete temporary demo SQLite databases that haven't been modified in $hoursOld hours,
     * while preserving the master template and persistent seed databases.
     */
    public static function cleanupStaleDatabases(int $hoursOld = 48): int
    {
        $demoDir = self::getDemoDirectory();
        if (! is_dir($demoDir)) {
            return 0;
        }

        $persistent = [
            '_template.sqlite',
            'nairobi-fresh-mart.sqlite',
            'nairobi-fresh-clean.sqlite',
            'metro-wholesale-retail.sqlite',
            'metro-wholesale-clean.sqlite',
        ];

        $cutoff = time() - ($hoursOld * 3600);
        $deleted = 0;

        $files = glob($demoDir . DIRECTORY_SEPARATOR . '*.sqlite');
        foreach ($files as $file) {
            $base = basename($file);
            if (in_array($base, $persistent)) {
                continue;
            }

            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
