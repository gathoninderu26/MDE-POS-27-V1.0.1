<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'total_purchases',
        'outstanding_balance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_purchases' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
