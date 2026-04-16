<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $expense_category_id
 * @property int|null $work_order_id
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property int|null $chart_of_account_id
 * @property int|null $reviewed_by
 * @property int|null $reimbursement_ap_id
 * @property string $description
 * @property float $amount
 * @property float|null $km_quantity
 * @property float|null $km_rate
 * @property bool $km_billed_to_client
 * @property Carbon|null $expense_date
 * @property string|null $payment_method
 * @property string|null $notes
 * @property string|null $receipt_path
 * @property bool $affects_technician_cash
 * @property bool $affects_net_value
 * @property Carbon|null $reviewed_at
 * @property ExpenseStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ExpenseCategory|null $category
 * @property-read WorkOrder|null $workOrder
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read User|null $reviewer
 * @property-read ChartOfAccount|null $chartOfAccount
 */
class Expense extends Model
{
    use Auditable, BelongsToTenant, Concerns\SetsCreatedBy, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function ($expense) {
            if (empty($expense->description)) {
                $expense->description = 'Despesa sem descrição';
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'expense_category_id',
        'work_order_id',
        'created_by',
        'approved_by',
        'chart_of_account_id',
        'description',
        'amount',
        'km_quantity',
        'km_rate',
        'km_billed_to_client',
        'expense_date',
        'payment_method',
        'notes',
        'receipt_path',
        'affects_technician_cash',
        'affects_net_value',
        'reviewed_by',
        'reviewed_at',
        'status',
        'rejection_reason',
        'reimbursement_ap_id',
        'payroll_id',
        'payroll_line_id',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'km_quantity' => 'decimal:1',
            'km_rate' => 'decimal:4',
            'km_billed_to_client' => 'boolean',
            'expense_date' => 'date',
            'affects_technician_cash' => 'boolean',
            'affects_net_value' => 'boolean',
            'reviewed_at' => 'datetime',
            'status' => ExpenseStatus::class,
        ];
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $normalized = is_string($value) ? trim($value) : $value;
        $this->attributes['description'] = blank($normalized) ? 'Despesa sem descrição' : $normalized;
    }

    /** @return array<string, array{label: string, color: string}> */
    public static function statuses(): array
    {
        return collect(ExpenseStatus::cases())
            ->mapWithKeys(fn (ExpenseStatus $s) => [$s->value => ['label' => $s->label(), 'color' => $s->color()]])
            ->all();
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ExpenseStatusHistory::class)->orderByDesc('created_at');
    }

    /**
     * @return BelongsTo<AccountPayable, $this>
     */
    public function reimbursementAccountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'reimbursement_ap_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }
}
