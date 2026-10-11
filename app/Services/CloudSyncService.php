<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SyncOutbox;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudSyncService
{
    protected ?string $supabaseUrl;
    protected ?string $supabaseKey;
    protected int $batchSize;
    protected int $retryLimit;

    public function __construct()
    {
        $this->supabaseUrl = config('tenant.sync.supabase_url');
        $this->supabaseKey = config('tenant.sync.supabase_key');
        $this->batchSize   = config('tenant.sync.batch_size', 50);
        $this->retryLimit  = config('tenant.sync.retry_limit', 5);
    }

    /**
     * Record a local change in the sync outbox (Store-and-Forward).
     */
    public function recordChange(
        int|string $tenantId,
        string $entityType,
        string $entityId,
        string $action,
        array $payload
    ): ?SyncOutbox {
        try {
            return SyncOutbox::create([
                'tenant_id'   => (int) $tenantId,
                'entity_type' => $entityType,
                'entity_id'   => (string) $entityId,
                'action'      => $action,
                'payload'     => $payload,
                'status'      => 'pending',
                'attempts'    => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to enqueue sync outbox item: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Push all pending outbox records to Supabase Cloud.
     */
    public function pushPendingToCloud(?int $tenantId = null, ?int $limit = null): array
    {
        $limit = $limit ?? $this->batchSize;

        $query = SyncOutbox::where('status', 'pending')
            ->where('attempts', '<', $this->retryLimit)
            ->orderBy('id', 'asc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'message' => 'No pending records'];
        }

        $synced = 0;
        $failed = 0;

        foreach ($records as $record) {
            $success = $this->syncSingleRecord($record);
            if ($success) {
                $record->update([
                    'status'    => 'synced',
                    'synced_at' => now(),
                    'error_message' => null,
                ]);
                $synced++;
            } else {
                $record->increment('attempts');
                $failed++;
            }
        }

        return [
            'processed' => $records->count(),
            'synced'    => $synced,
            'failed'    => $failed,
        ];
    }

    /**
     * Dispatch single record to Supabase via REST PostgREST API or direct PostgreSQL.
     */
    protected function syncSingleRecord(SyncOutbox $record): bool
    {
        // 1. If Supabase REST credentials exist, use resilient HTTPS PostgREST
        if (!empty($this->supabaseUrl) && !empty($this->supabaseKey)) {
            try {
                $table = $this->resolveCloudTableName($record->entity_type);
                $endpoint = rtrim($this->supabaseUrl, '/') . "/rest/v1/{$table}";

                $headers = [
                    'apikey'        => $this->supabaseKey,
                    'Authorization' => "Bearer {$this->supabaseKey}",
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'resolution=merge-duplicates',
                ];

                $payload = array_merge($record->payload, [
                    'tenant_id' => $record->tenant_id,
                    'synced_at' => now()->toIso8601String(),
                ]);

                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    return true;
                }

                $record->update(['error_message' => "HTTP {$response->status()}: " . $response->body()]);
                return false;
            } catch (\Throwable $e) {
                $record->update(['error_message' => $e->getMessage()]);
                return false;
            }
        }

        // 2. Fallback: Direct database connection if 'supabase' PDO connection configured
        if ($this->hasSupabaseDbConnection()) {
            try {
                $table = $this->resolveCloudTableName($record->entity_type);
                DB::connection('supabase')->table($table)->updateOrInsert(
                    ['id' => $record->payload['id'] ?? $record->entity_id, 'tenant_id' => $record->tenant_id],
                    $record->payload
                );
                return true;
            } catch (\Throwable $e) {
                $record->update(['error_message' => $e->getMessage()]);
                return false;
            }
        }

        $record->update(['error_message' => 'Supabase URL/Key or Database connection not configured in .env']);
        return false;
    }

    /**
     * Pull catalog & product master data from Supabase Cloud down to local MySQL.
     */
    public function pullFromCloud(int $tenantId): array
    {
        if (empty($this->supabaseUrl) || empty($this->supabaseKey)) {
            return ['success' => false, 'message' => 'Supabase credentials not configured'];
        }

        try {
            $endpoint = rtrim($this->supabaseUrl, '/') . "/rest/v1/products?tenant_id=eq.{$tenantId}&select=*";

            $response = Http::withHeaders([
                'apikey'        => $this->supabaseKey,
                'Authorization' => "Bearer {$this->supabaseKey}",
            ])->timeout(15)->get($endpoint);

            if (!$response->successful()) {
                return ['success' => false, 'message' => "Cloud fetch failed: {$response->status()}"];
            }

            $products = $response->json();
            $updated = 0;

            foreach ($products as $p) {
                Product::updateOrCreate(
                    ['tenant_id' => $tenantId, 'barcode' => $p['barcode'] ?? null, 'name' => $p['name']],
                    [
                        'sku'           => $p['sku'] ?? null,
                        'category'      => $p['category'] ?? 'General',
                        'cost_price'    => $p['cost_price'] ?? 0,
                        'selling_price' => $p['selling_price'] ?? 0,
                        'stock_quantity'=> $p['stock_quantity'] ?? 0,
                        'reorder_level' => $p['reorder_level'] ?? 5,
                        'unit'          => $p['unit'] ?? 'Piece',
                        'status'        => $p['status'] ?? 'Active',
                    ]
                );
                $updated++;
            }

            return ['success' => true, 'updated_products' => $updated];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check current sync status for local dashboard/header indicator.
     */
    public function getSyncStatus(?int $tenantId = null): array
    {
        $query = SyncOutbox::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $pending = (clone $query)->where('status', 'pending')->count();
        $failed  = (clone $query)->where('status', 'failed')->count();
        $lastSynced = (clone $query)->where('status', 'synced')->latest('synced_at')->value('synced_at');

        $isOnline = false;
        if (!empty($this->supabaseUrl)) {
            try {
                $res = Http::timeout(3)->get(rtrim($this->supabaseUrl, '/') . '/rest/v1/');
                $isOnline = $res->status() < 500;
            } catch (\Throwable) {
                $isOnline = false;
            }
        }

        return [
            'is_configured' => !empty($this->supabaseUrl) && !empty($this->supabaseKey),
            'is_online'     => $isOnline,
            'pending_count' => $pending,
            'failed_count'  => $failed,
            'last_synced_at'=> $lastSynced,
            'sync_mode'     => config('tenant.mode', 'multi'),
        ];
    }

    protected function resolveCloudTableName(string $entityType): string
    {
        return match ($entityType) {
            'sale'             => 'sales',
            'sale_item'        => 'sale_items',
            'product'          => 'products',
            'expense'          => 'expenses',
            'customer'         => 'customers',
            'supplier'         => 'suppliers',
            'stock_adjustment' => 'stock_adjustments',
            default            => strtolower($entityType) . 's',
        };
    }

    protected function hasSupabaseDbConnection(): bool
    {
        return !empty(config('database.connections.supabase.host')) &&
               !empty(config('database.connections.supabase.password'));
    }
}
