<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'group',
        'credit_limit',
        'current_balance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
