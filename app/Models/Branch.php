<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'location',
        'phone',
        'manager',
        'current_stock_value',
        'operating_hours',
        'wifi_ssid',
        'status',
        'is_main',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'current_stock_value' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function clockSessions(): HasMany
    {
        return $this->hasMany(ClockSession::class);
    }
}
