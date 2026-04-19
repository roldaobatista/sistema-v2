<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use App\Enums\FinancialStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use App\Traits\SyncsWithAgenda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property int|null $work_order_id
 * @property int|null $quote_id
 * @property string|null $origin_type
 * @property int|null $invoice_id
 * @property int|null $created_by
 * @property int|null $chart_of_account_id
 * @property int|null $cost_center_id
 * @property string|null $description
 * @property float $amount
 * @property float $amount_paid
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property FinancialStatus $status
 * @property string|null $payment_method
 * @property string|null $notes
 * @property string|null $nosso_numero
 * @property string|null $numero_documento
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Customer|null $customer
 * @property-read WorkOrder|null $workOrder
 * @property-read Quote|null $quote
 * @property-read Invoice|null $invoice
 * @property-read ChartOfAccount|null $chartOfAccount
 * @property-read User|null $creator
 * @property-read Collection<int, Payment> $payments
 * @property numeric-string|null $penalty_amount
 * @property numeric-string|null $interest_amount
 * @property numeric-string|null $discount_amount
 */
class AccountReceivable extends Model
{
    use Auditable, BelongsToTenant, Concerns\HasAuditUserFields, Concerns\SetsCreatedBy, HasFactory, SoftDeletes, SyncsWithAgenda;

    protected $table = 'accounts_receivable';

    protected $fillable = [
        'tenant_id', 'customer_id', 'work_order_id', 'quote_id', 'invoice_id',
        'chart_of_account_id', 'origin_type', 'cost_center_id',
        'reference_id',
        'description', 'amount', 'amount_paid', 'due_date', 'paid_at',
        'penalty_amount', 'interest_amount', 'discount_amount',
        'status', 'payment_method', 'notes',
        'nosso_numero', 'numero_documento',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
            'status' => FinancialStatus::class,
        ];
    }

    // ── Status (via Enum — constantes mantidas para backward compat) ──
    /** @deprecated Use FinancialStatus::PENDING */
    public const STATUS_PENDING = 'pending';

    /** @deprecated Use FinancialStatus::PARTIAL */
    public const STATUS_PARTIAL = 'partial';

    /** @deprecated Use FinancialStatus::PAID */
    public const STATUS_PAID = 'paid';

    /** @deprecated Use FinancialStatus::OVERDUE */
    public const STATUS_OVERDUE = 'overdue';

    /** @deprecated Use FinancialStatus::CANCELLED */
    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Use FinancialStatus::RENEGOTIATED */
    public const STATUS_RENEGOTIATED = 'renegotiated';

    /** @return array<string, array{label: string, color: string}> */
    public static function statuses(): array
    {
        return collect(FinancialStatus::cases())
            ->mapWithKeys(fn (FinancialStatus $s) => [$s->value => ['label' => $s->label(), 'color' => $s->color()]])
            ->all();
    }

    /** @deprecated Use FinancialStatus::cases() or self::statuses() */
    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'Pendente', 'color' => 'warning'],
        self::STATUS_PARTIAL => ['label' => 'Parcial', 'color' => 'info'],
        self::STATUS_PAID => ['label' => 'Pago', 'color' => 'success'],
        self::STATUS_OVERDUE => ['label' => 'Vencido', 'color' => 'danger'],
        self::STATUS_CANCELLED => ['label' => 'Cancelado', 'color' => 'default'],
    ];

    public const PAYMENT_METHODS = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'PIX',
        'cartao_credito' => 'Cartão Crédito',
        'cartao_debito' => 'Cartão Débito',
        'boleto' => 'Boleto',
        'transferencia' => 'Transferência',
    ];

    public function recalculateStatus(): void
    {
        // Terminal statuses should not have their status recalculated
        $currentStatus = $this->status instanceof FinancialStatus ? $this->status : FinancialStatus::tryFrom($this->status);
        if (in_array($currentStatus, [FinancialStatus::RENEGOTIATED, FinancialStatus::CANCELLED, FinancialStatus::RECEIVED], true)) {
            return;
        }

        // Net amount due = amount + penalties + interest - discounts
        $netAmount = bcsub(
            bcadd(Decimal::string($this->amount), bcadd(Decimal::string($this->penalty_amount), Decimal::string($this->interest_amount), 2), 2),
            Decimal::string($this->discount_amount),
            2
        );
        $remaining = bcsub($netAmount, Decimal::string($this->amount_paid), 2);

        if (bccomp($remaining, '0', 2) <= 0) {
            $statusValue = FinancialStatus::PAID->value;
            $paidAt = $this->paid_at ?? now();
        } elseif (($dueDate = $this->normalizedDate($this->due_date)) && $dueDate->isBefore(today())) {
            $statusValue = FinancialStatus::OVERDUE->value;
            $paidAt = null;
        } elseif (bccomp(Decimal::string($this->amount_paid), '0', 2) > 0) {
            $statusValue = FinancialStatus::PARTIAL->value;
            $paidAt = null;
        } else {
            $statusValue = FinancialStatus::PENDING->value;
            $paidAt = null;
        }

        $currentStatus = $this->status instanceof FinancialStatus ? $this->status->value : $this->status;
        $hasChanged = $currentStatus !== $statusValue
            || (($this->paid_at?->toDateString()) !== ($paidAt?->toDateString()));

        if ($hasChanged) {
            $this->forceFill([
                'status' => $statusValue,
                'paid_at' => $paidAt,
            ])->saveQuietly();
        }
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    private function normalizedDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * @return HasMany<AccountReceivableInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(AccountReceivableInstallment::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function centralSyncData(): array
    {
        $statusMap = [
            FinancialStatus::PENDING->value => AgendaItemStatus::ABERTO,
            FinancialStatus::PARTIAL->value => AgendaItemStatus::EM_ANDAMENTO,
            FinancialStatus::PAID->value => AgendaItemStatus::CONCLUIDO,
            FinancialStatus::OVERDUE->value => AgendaItemStatus::ABERTO,
            FinancialStatus::CANCELLED->value => AgendaItemStatus::CANCELADO,
            FinancialStatus::RENEGOTIATED->value => AgendaItemStatus::CANCELADO,
        ];

        $statusValue = $this->status instanceof FinancialStatus ? $this->status->value : $this->status;

        return [
            'status' => $statusMap[$statusValue] ?? AgendaItemStatus::ABERTO,
        ];
    }
}
