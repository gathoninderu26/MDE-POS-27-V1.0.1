<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'purchase_id',
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'tax_pin',
        'control_unit_code',
        'subtotal',
        'tax_amount',
        'total_amount',
        'po_expected_total',
        'variance_amount',
        'raw_qr_payload',
        'qr_metadata',
        'extracted_items',
        'reconciliation_matrix',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'reconciliation_status',
        'status',
        'verified_by_user_id',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'po_expected_total' => 'decimal:2',
            'variance_amount' => 'decimal:2',
            'qr_metadata' => 'array',
            'extracted_items' => 'array',
            'reconciliation_matrix' => 'array',
            'verified_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
