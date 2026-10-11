<?php

namespace App\Console\Commands;

use App\Services\CloudSyncService;
use Illuminate\Console\Command;

class SyncCloudCommand extends Command
{
    protected $signature = 'sync:cloud {--tenant= : Specific tenant ID to sync} {--pull : Also pull catalog updates from Supabase}';
    protected $description = 'Synchronize local MySQL outbox records to Supabase Cloud';

    public function handle(CloudSyncService $syncService): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info('Starting cloud synchronization with Supabase...');

        $status = $syncService->getSyncStatus($tenantId);
        $this->line("• Local Pending: {$status['pending_count']} records");
        $this->line("• Cloud Reachable: " . ($status['is_online'] ? 'Yes (Online)' : 'No (Offline)'));

        $results = $syncService->pushPendingToCloud($tenantId);
        $this->info("• Processed: {$results['processed']} | Synced: {$results['synced']} | Failed: {$results['failed']}");

        if ($this->option('pull') && $tenantId) {
            $this->info("Pulling catalog updates from Supabase...");
            $pullRes = $syncService->pullFromCloud($tenantId);
            if ($pullRes['success'] ?? false) {
                $this->info("• Updated products: " . ($pullRes['updated_products'] ?? 0));
            } else {
                $this->warn("• Pull failed: " . ($pullRes['message'] ?? $pullRes['error'] ?? 'Unknown'));
            }
        }

        $this->info('Cloud synchronization completed.');
        return Command::SUCCESS;
    }
}
