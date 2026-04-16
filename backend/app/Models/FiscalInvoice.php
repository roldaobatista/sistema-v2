<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $total
 * @property Carbon|null $issued_at
 */
class FiscalInvoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function ($invoice) {
            $exists = static::where('tenant_id', $invoice->tenant_id)
                ->where('number', $invoice->number)
                ->exists();
            if ($exists) {
                throw new QueryException(
                    'mysql',
                    "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '{$invoice->number}'",
                    [],
                    new \Exception
                );
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'number', 'series', 'type', 'customer_id', 'work_order_id', 'total', 'status', 'issued_at', 'xml', 'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FiscalInvoiceItem::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
