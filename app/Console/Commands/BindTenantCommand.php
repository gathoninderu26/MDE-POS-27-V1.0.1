<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\CloudSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BindTenantCommand extends Command
{
    protected $signature = 'tenant:bind 
                            {tenant? : Tenant ID or Slug to lock this PC to}
                            {--slug= : Tenant slug}
                            {--id= : Tenant ID}
                            {--license= : Store activation license key}
                            {--pull : Pull product catalog from Supabase cloud after binding}';

    protected $description = 'Lock this computer (XAMPP/Localhost) to a single dedicated tenant account';

    public function handle(CloudSyncService $syncService): int
    {
        $identifier = $this->argument('tenant') ?? $this->option('slug') ?? $this->option('id');

        if (! $identifier) {
            $this->info('Available Tenants in local database:');
            $tenants = Tenant::all(['id', 'name', 'slug', 'business_type']);
            if ($tenants->isEmpty()) {
                $this->error('No tenants exist in local database. Please run migrations and seeders or connect to cloud first.');

                return Command::FAILURE;
            }

            foreach ($tenants as $t) {
                $this->line("• ID: {$t->id} | Slug: {$t->slug} | Name: {$t->name} ({$t->business_type})");
            }

            $identifier = $this->ask('Enter the Tenant ID or Slug to lock this PC to:');
        }

        $tenant = is_numeric($identifier)
            ? Tenant::find($identifier)
            : Tenant::where('slug', $identifier)->first();

        if (! $tenant) {
            $this->error("Tenant \"{$identifier}\" not found.");

            return Command::FAILURE;
        }

        $license = $this->option('license') ?? 'LIC-'.strtoupper(substr(md5($tenant->slug.microtime()), 0, 12));

        // Update local .env
        $this->updateEnvFile([
            'TENANT_MODE' => 'single',
            'LOCKED_TENANT_ID' => (string) $tenant->id,
            'LOCKED_TENANT_SLUG' => $tenant->slug,
            'TENANT_LICENSE_KEY' => $license,
        ]);

        $this->call('config:clear');

        $this->info('✓ PC Hardware Lock Applied Successfully!');
        $this->info("• Store: {$tenant->name}");
        $this->info("• Tenant ID: {$tenant->id}");
        $this->info("• Locked Slug: {$tenant->slug}");
        $this->info("• License: {$license}");
        $this->line("This PC will now strictly run {$tenant->name} without requiring subdomains or allowing cross-tenant switching.");

        if ($this->option('pull')) {
            $this->info('Pulling catalog from Supabase...');
            $pull = $syncService->pullFromCloud($tenant->id);
            if ($pull['success'] ?? false) {
                $this->info("• Synchronized {$pull['updated_products']} products from cloud.");
            } else {
                $this->warn('• Cloud pull: '.($pull['message'] ?? 'Skipped'));
            }
        }

        return Command::SUCCESS;
    }

    protected function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);

        foreach ($values as $key => $val) {
            $pattern = "/^{$key}=(.*)$/m";
            $entry = "{$key}=\"{$val}\"";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $entry, $content);
            } else {
                $content .= "\n{$entry}";
            }
        }

        File::put($envPath, $content);
    }
}
