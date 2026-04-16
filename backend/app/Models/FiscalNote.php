<?php

namespace App\Models;

use App\Enums\FiscalNoteStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $total_amount
 * @property Carbon|null $issued_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $last_email_sent_at
 * @property int|null $email_retry_count
 * @property array<int|string, mixed>|null $raw_response
 * @property array<int|string, mixed>|null $items_data
 * @property array<int|string, mixed>|null $payment_data
 * @property bool|null $contingency_mode
 * @property FiscalNoteStatus|null $status
 */
class FiscalNote extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    // ── Status (via Enum — constantes mantidas para backward compat) ──
    /** @deprecated Use FiscalNoteStatus::PENDING */
    const STATUS_PENDING = 'pending';

    /** @deprecated Use FiscalNoteStatus::PROCESSING */
    const STATUS_PROCESSING = 'processing';

    /** @deprecated Use FiscalNoteStatus::AUTHORIZED */
    const STATUS_AUTHORIZED = 'authorized';

    /** @deprecated Use FiscalNoteStatus::CANCELLED */
    const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Use FiscalNoteStatus::REJECTED */
    const STATUS_REJECTED = 'rejected';

    const TYPE_NFE = 'nfe';

    const TYPE_NFSE = 'nfse';

    const TYPE_NFE_DEVOLUCAO = 'nfe_devolucao';

    const TYPE_NFE_COMPLEMENTAR = 'nfe_complementar';

    const TYPE_NFE_REMESSA = 'nfe_remessa';

    const TYPE_NFE_RETORNO = 'nfe_retorno';

    const TYPE_CTE = 'cte';

    protected $fillable = [
        'tenant_id',
        'type',
        'work_order_id',
        'quote_id',
        'parent_note_id',
        'customer_id',
        'number',
        'series',
        'access_key',
        'reference',
        'status',
        'provider',
        'provider_id',
        'total_amount',
        'nature_of_operation',
        'cfop',
        'items_data',
        'payment_data',
        'protocol_number',
        'environment',
        'contingency_mode',
        'email_retry_count',
        'last_email_sent_at',
        'verification_code',
        'issued_at',
        'cancelled_at',
        'cancel_reason',
        'pdf_url',
        'pdf_path',
        'xml_url',
        'xml_path',
        'error_message',
        'raw_response',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_email_sent_at' => 'datetime',
            'email_retry_count' => 'integer',
            'raw_response' => 'array',
            'items_data' => 'array',
            'payment_data' => 'array',
            'contingency_mode' => 'boolean',
            'status' => FiscalNoteStatus::class,
        ];
    }

    // ─── Relationships ──────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function events(): HasMany
    {
        return $this->hasMany(FiscalEvent::class)->orderByDesc('created_at');
    }

    public function parentNote(): BelongsTo
    {
        return $this->belongsTo(FiscalNote::class, 'parent_note_id');
    }

    public function childNotes(): HasMany
    {
        return $this->hasMany(FiscalNote::class, 'parent_note_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'work_order_id', 'work_order_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(FiscalAuditLog::class);
    }

    public function hasPdf(): bool
    {
        return ! empty($this->pdf_path) || ! empty($this->pdf_url);
    }

    public function hasXml(): bool
    {
        return ! empty($this->xml_path) || ! empty($this->xml_url);
    }

    // ─── Scopes ─────────────────────────────────────

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAuthorized($query)
    {
        return $query->where('status', FiscalNoteStatus::AUTHORIZED->value);
    }

    // ─── Helpers ────────────────────────────────────

    public function isPending(): bool
    {
        return in_array($this->status, [FiscalNoteStatus::PENDING, FiscalNoteStatus::PROCESSING]);
    }

    public function isAuthorized(): bool
    {
        return $this->status === FiscalNoteStatus::AUTHORIZED;
    }

    public function isCancelled(): bool
    {
        return $this->status === FiscalNoteStatus::CANCELLED;
    }

    public function canCancel(): bool
    {
        if ($this->status !== FiscalNoteStatus::AUTHORIZED) {
            return false;
        }

        // NF-e: SEFAZ enforces a 24h cancellation window from authorization
        if ($this->isNFe() && $this->issued_at) {
            $hoursLimit = 24;
            if ($this->issued_at->diffInHours(now()) > $hoursLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the reason why cancellation is not allowed.
     */
    public function cancelDeniedReason(): ?string
    {
        if ($this->status !== FiscalNoteStatus::AUTHORIZED) {
            return 'Apenas notas autorizadas podem ser canceladas (status: '.($this->status instanceof FiscalNoteStatus ? $this->status->value : $this->status).')';
        }

        if ($this->isNFe() && $this->issued_at && $this->issued_at->diffInHours(now()) > 24) {
            return 'Prazo de cancelamento de NF-e expirado (24h). Utilize Carta de Correção ou emita uma NF-e de devolução.';
        }

        return null;
    }

    public function canCorrect(): bool
    {
        return $this->type === self::TYPE_NFE && $this->status === FiscalNoteStatus::AUTHORIZED;
    }

    public function isNFe(): bool
    {
        return $this->type === self::TYPE_NFE;
    }

    public function isNFSe(): bool
    {
        return $this->type === self::TYPE_NFSE;
    }

    /**
     * Generate a unique reference for Focus NFe API.
     */
    public static function generateReference(string $type, int $tenantId): string
    {
        return "{$type}_{$tenantId}_".now()->format('YmdHis').'_'.bin2hex(random_bytes(4));
    }
}
