<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DemoDatabaseService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the master demo SQLite template and optionally refreshes
 * individual per-tenant demo database files.
 *
 * Usage:
 *   php artisan demo:refresh              — rebuilds _template.sqlite only
 *   php artisan demo:refresh --all        — rebuilds template AND re-copies to all slug files
 *   php artisan demo:refresh --slug=demo-store  — rebuilds template and re-copies to one slug
 */
class DemoRefresh extends Command
{
    protected $signature = 'demo:refresh
                            {--all : Re-copy the refreshed template to every existing demo slug file}
                            {--slug= : Re-copy the refreshed template to a specific slug only}';

    protected $description = 'Rebuild the demo SQLite template and optionally refresh per-tenant demo databases';

    public function handle(): int
    {
        $demoDir = database_path('demo');
        $template = $demoDir.DIRECTORY_SEPARATOR.'_template.sqlite';

        // Ensure the demo directory exists
        if (! is_dir($demoDir)) {
            mkdir($demoDir, 0755, true);
            $this->info("Created demo directory: {$demoDir}");
        }

        // ── Step 1: Create a fresh blank template file ────────────────────────
        if (file_exists($template)) {
            unlink($template);
        }
        touch($template);
        $this->info('Created blank template: _template.sqlite');

        // ── Step 2: Point the 'demo' connection at the blank template ─────────
        Config::set('database.connections.demo.database', $template);
        DB::purge('demo');

        // ── Step 3: Run all migrations on the template ────────────────────────
        $this->info('Running migrations on template...');
        Artisan::call('migrate', [
            '--database' => 'demo',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        // ── Step 4: Seed with realistic demo data ─────────────────────────────
        $this->info('Seeding demo data...');
        Artisan::call('db:seed', [
            '--database' => 'demo',
            '--class' => DemoDataSeeder::class,
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('✅ Template rebuilt: '.$template);

        // ── Step 5: Optionally re-copy to per-slug files ──────────────────────
        if ($this->option('all')) {
            $this->refreshAllSlugs($demoDir, $template);
        } elseif ($slug = $this->option('slug')) {
            $this->refreshSlug($demoDir, $template, $slug);
        }

        return self::SUCCESS;
    }

    protected function refreshAllSlugs(string $demoDir, string $template): void
    {
        $files = glob($demoDir.DIRECTORY_SEPARATOR.'*.sqlite');
        foreach ($files as $file) {
            $name = basename($file, '.sqlite');
            if ($name === '_template') {
                continue;
            }
            $this->refreshSlug($demoDir, $template, $name);
        }
    }

    protected function refreshSlug(string $demoDir, string $template, string $slug): void
    {
        $target = $demoDir.DIRECTORY_SEPARATOR.$slug.'.sqlite';
        copy($template, $target);

        $tenant = Tenant::where('slug', $slug)->first();
        if ($tenant) {
            DemoDatabaseService::adaptDatabaseForTenant($target, $tenant);
        }

        $this->info("  ↳ Refreshed: {$slug}.sqlite");
    }
}
