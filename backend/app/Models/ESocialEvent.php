<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $sent_at
 * @property Carbon|null $response_at
 * @property Carbon|null $last_retry_at
 * @property Carbon|null $next_retry_at
 * @property int|null $retry_count
 * @property int|null $max_retries
 */
class ESocialEvent extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'esocial_events';

    public const EVENT_TYPES = [
        'S-1000' => 'Informações do Empregador/Contribuinte',
        'S-1010' => 'Tabela de Rubricas',
        'S-1200' => 'Remuneração de Trabalhador',
        'S-1210' => 'Pagamentos de Rendimentos do Trabalho',
        'S-2200' => 'Cadastramento Inicial / Admissão',
        'S-2205' => 'Alteração de Dados Cadastrais',
        'S-2206' => 'Alteração de Contrato de Trabalho',
        'S-2210' => 'Comunicação de Acidente de Trabalho (CAT)',
        'S-2220' => 'Monitoramento da Saúde do Trabalhador (ASO)',
        'S-2230' => 'Afastamento Temporário',
        'S-2240' => 'Condições Ambientais do Trabalho',
        'S-2299' => 'Desligamento',
        'S-3000' => 'Exclusão de Eventos',
    ];

    public const STATUSES = [
        'pending' => 'Pendente',
        'generating' => 'Gerando XML',
        'sent' => 'Enviado',
        'accepted' => 'Aceito',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = [
        'tenant_id',
        'event_type',
        'related_type',
        'related_id',
        'xml_content',
        'protocol_number',
        'receipt_number',
        'status',
        'response_xml',
        'sent_at',
        'response_at',
        'error_message',
        'batch_id',
        'environment',
        'version',
        'retry_count',
        'max_retries',
        'last_retry_at',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'response_at' => 'datetime',
            'last_retry_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'xml_content' => 'string',
            'response_xml' => 'string',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
        ];

    }

    // ── Relationships ──

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    // ── Helpers ──

    public function getEventLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    // ── Retry Logic ──

    /**
     * Determine if this event should be retried.
     */
    public function shouldRetry(): bool
    {
        if ($this->status !== 'rejected') {
            return false;
        }

        if ($this->retry_count >= $this->max_retries) {
            return false;
        }

        if ($this->next_retry_at && now()->lt($this->next_retry_at)) {
            return false;
        }

        return true;
    }

    /**
     * Mark the event for retry with exponential backoff.
     */
    public function markForRetry(?string $errorMessage = null): self
    {
        $backoffMinutes = (int) pow(2, $this->retry_count) * 5; // 5, 10, 20, 40...

        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
            'next_retry_at' => now()->addMinutes($backoffMinutes),
            'status' => 'pending',
            'error_message' => $errorMessage ?? $this->error_message,
        ]);

        return $this;
    }

    /**
     * Check if max retries have been exhausted.
     */
    public function hasExhaustedRetries(): bool
    {
        return $this->retry_count >= $this->max_retries;
    }

    /**
     * Scope: retryable events (rejected and under max retries with backoff elapsed).
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', 'rejected')
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }
}
