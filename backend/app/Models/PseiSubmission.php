<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $request_payload
 * @property array<int|string, mixed>|null $response_payload
 * @property Carbon|null $submitted_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $next_retry_at
 * @property int|null $attempt_number
 * @property int|null $max_attempts
 */
class PseiSubmission extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SUBMITTING = 'submitting';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMEOUT = 'timeout';

    public const STATUS_CAPTCHA_BLOCKED = 'captcha_blocked';

    public const TYPE_AUTOMATIC = 'automatic';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_RETRY = 'retry';

    protected $fillable = [
        'tenant_id',
        'seal_id',
        'work_order_id',
        'equipment_id',
        'submission_type',
        'status',
        'attempt_number',
        'max_attempts',
        'protocol_number',
        'request_payload',
        'response_payload',
        'error_message',
        'submitted_at',
        'confirmed_at',
        'next_retry_at',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'attempt_number' => 'integer',
            'max_attempts' => 'integer',
        ];

    }

    // ─── Relationships ──────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function seal(): BelongsTo
    {
        return $this->belongsTo(InmetroSeal::class, 'seal_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_SUBMITTING]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_TIMEOUT, self::STATUS_CAPTCHA_BLOCKED]);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->failed()
            ->whereColumn('attempt_number', '<', 'max_attempts')
            ->where(function (Builder $q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getCanRetryAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_TIMEOUT, self::STATUS_CAPTCHA_BLOCKED])
            && $this->attempt_number < $this->max_attempts;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Na Fila',
            self::STATUS_SUBMITTING => 'Enviando',
            self::STATUS_SUCCESS => 'Sucesso',
            self::STATUS_FAILED => 'Falhou',
            self::STATUS_TIMEOUT => 'Timeout',
            self::STATUS_CAPTCHA_BLOCKED => 'CAPTCHA Bloqueado',
            default => $this->status,
        };
    }

    // ─── Methods ────────────────────────────────────────────

    public function markAsSubmitting(): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTING,
            'submitted_at' => now(),
        ]);
    }

    public function markAsSuccess(string $protocolNumber): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'protocol_number' => $protocolNumber,
            'confirmed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error, ?string $status = null): void
    {
        $backoffMinutes = [1, 5, 30][$this->attempt_number - 1] ?? 60;

        $this->update([
            'status' => $status ?? self::STATUS_FAILED,
            'error_message' => $error,
            'next_retry_at' => $this->attempt_number < $this->max_attempts
                ? now()->addMinutes($backoffMinutes)
                : null,
        ]);
    }

    public function incrementAttempt(): void
    {
        $this->increment('attempt_number');
    }
}
