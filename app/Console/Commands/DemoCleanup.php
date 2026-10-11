<?php

namespace App\Console\Commands;

use App\Services\DemoDatabaseService;
use Illuminate\Console\Command;

class DemoCleanup extends Command
{
    protected $signature = 'demo:cleanup {--hours=48 : Delete demo files older than this many hours}';
    protected $description = 'Prune temporary demo SQLite files to prevent disk exhaustion';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Scanning demo directory for files older than {$hours} hours...");

        $deleted = DemoDatabaseService::cleanupStaleDatabases($hours);

        $this->info("✅ Pruned {$deleted} stale demo SQLite file(s). Persistent seeds & master template preserved.");

        return self::SUCCESS;
    }
}
