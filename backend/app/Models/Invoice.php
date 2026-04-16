<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $work_order_id
 * @property int|null $customer_id
 * @property int|null $created_by
 * @property string|null $invoice_number
 * @property string|null $nf_number
 * @property InvoiceStatus $status
 * @property float $total
 * @property float|null $discount
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_date
 * @property string|null $observations
 * @property array|null $items
 * @property string|null $fiscal_status
 * @property string|null $fiscal_note_key
 * @property Carbon|null $fiscal_emitted_at
 * @property string|null $fiscal_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read WorkOrder|null $workOrder
 * @property-read Customer|null $customer
 * @property-read User|null $creator
 * @property-read FiscalNote|null $fiscalNote
 * @property-read Collection<int, AccountReceivable> $accountsReceivable
 */
class Invoice extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_ISSUED => 'Emitida',
        self::STATUS_SENT => 'Enviada',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    public const FISCAL_STATUS_EMITTING = 'emitting';

    public const FISCAL_STATUS_EMITTED = 'emitted';

    public const FISCAL_STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'work_order_id', 'customer_id', 'created_by',
        'invoice_number', 'nf_number', 'status', 'total', 'discount',
        'issued_at', 'due_date', 'observations', 'items',
        'fiscal_status', 'fiscal_note_key', 'fiscal_emitted_at', 'fiscal_error',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'issued_at' => 'date',
            'due_date' => 'date',
            'fiscal_emitted_at' => 'datetime',
            'items' => 'array',
            'status' => InvoiceStatus::class,
        ];
    }

    public static function nextNumber(int $tenantId): string
    {
        // Use lockForUpdate to prevent race condition on concurrent invoice creation
        $last = static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('invoice_number');
        $seq = $last ? (int) str_replace('NF-', '', $last) + 1 : 1;

        return 'NF-'.str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fiscalNote(): HasOne
    {
        return $this->hasOne(FiscalNote::class, 'work_order_id', 'work_order_id');
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountReceivable::class, 'invoice_id');
    }
}
