<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOutbox extends Model
{
    use HasFactory;

    protected $table = 'sync_outbox';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'status',
        'attempts',
        'error_message',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
