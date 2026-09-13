<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'default_unit',
        'default_tax_rate',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Products that belong to this category (matched by name string).
     * When category_id column is not yet added to products, we match by name.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id', 'tenant_id')
            ->where('category', $this->name);
    }
}
