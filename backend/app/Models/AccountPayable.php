<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use App\Enums\FinancialStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use App\Traits\SyncsWithAgenda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $created_by
 * @property int|null $supplier_id
 * @property int|null $category_id
 * @property int|null $chart_of_account_id
 * @property int|null $work_order_id
 * @property string|null $description
 * @property float $amount
 * @property float $amount_paid
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property FinancialStatus $status
 * @property string|null $payment_method
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Supplier|null $supplier
 * @property-read AccountPayableCategory|null $category
 * @property-read ChartOfAccount|null $chartOfAccount
 * @property-read WorkOrder|null $workOrder
 * @property-read User|null $createdBy
 * @property numeric-string|null $penalty_amount
 * @property numeric-string|null $interest_amount
 * @property numeric-string|null $discount_amount
 */
class AccountPayable extends Model
{
    use Auditable, BelongsToTenant, Concerns\HasAuditUserFields, Concerns\SetsCreatedBy, HasFactory, SoftDeletes, SyncsWithAgenda;

    protected $table = 'accounts_payable';

    protected $fillable = [
        'tenant_id', 'created_by', 'updated_by', 'deleted_by',
        'supplier_id', 'category_id',
        'chart_of_account_id', 'cost_center_id', 'work_order_id',
        'description', 'amount', 'amount_paid', 'due_date', 'paid_at',
        'penalty_amount', 'interest_amount', 'discount_amount',
        'status', 'payment_method', 'notes',
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

    public const CATEGORIES = [
        'fornecedor' => 'Fornecedor',
        'aluguel' => 'Aluguel',
        'salario' => 'Salário',
        'imposto' => 'Imposto',
        'servico' => 'Serviço',
        'manutencao' => 'Manutenção',
        'outros' => 'Outros',
    ];

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

    public function recalculateStatus(): void
    {
        // Terminal statuses should not be recalculated
        $currentStatus = $this->status instanceof FinancialStatus ? $this->status : FinancialStatus::tryFrom((string) $this->status);
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
            $status = FinancialStatus::PAID;
            $paidAt = $this->paid_at ?? now();
        } elseif (($dueDate = $this->normalizedDate($this->due_date)) && $dueDate->isBefore(today())) {
            $status = FinancialStatus::OVERDUE;
            $paidAt = null;
        } elseif (bccomp(Decimal::string($this->amount_paid), '0', 2) > 0) {
            $status = FinancialStatus::PARTIAL;
            $paidAt = null;
        } else {
            $status = FinancialStatus::PENDING;
            $paidAt = null;
        }

        $currentStatusValue = $this->status instanceof FinancialStatus ? $this->status->value : (string) $this->status;
        $hasChanged = $currentStatusValue !== $status->value
            || (($this->paid_at?->toDateString()) !== ($paidAt?->toDateString()));

        if ($hasChanged) {
            $this->forceFill([
                'status' => $status->value,
                'paid_at' => $paidAt,
            ])->saveQuietly();
        }
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplierRelation(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(AccountPayableCategory::class, 'category_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * @return HasMany<AccountPayableInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(AccountPayableInstallment::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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

    public function centralSyncData(): array
    {
        $statusMap = [
            FinancialStatus::PENDING->value => AgendaItemStatus::ABERTO,
            FinancialStatus::PARTIAL->value => AgendaItemStatus::EM_ANDAMENTO,
            FinancialStatus::PAID->value => AgendaItemStatus::CONCLUIDO,
            FinancialStatus::OVERDUE->value => AgendaItemStatus::ABERTO,
            FinancialStatus::CANCELLED->value => AgendaItemStatus::CANCELADO,
        ];

        $statusValue = $this->status instanceof FinancialStatus ? $this->status->value : $this->status;

        return [
            'status' => $statusMap[$statusValue] ?? AgendaItemStatus::ABERTO,
        ];
    }
}
