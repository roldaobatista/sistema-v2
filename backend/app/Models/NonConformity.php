<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_date
 * @property Carbon|null $closed_at
 */
class NonConformity extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'non_conformities';

    protected $fillable = [
        'tenant_id', 'nc_number', 'title', 'description', 'source', 'severity',
        'status', 'reported_by', 'assigned_to', 'due_date', 'closed_at',
        'root_cause', 'corrective_action', 'preventive_action', 'verification_notes',
        'capa_record_id', 'quality_audit_id',
    ];

    public const SOURCES = [
        'audit' => 'Auditoria',
        'customer_complaint' => 'Reclamação de Cliente',
        'process_deviation' => 'Desvio de Processo',
    ];

    public const SEVERITIES = [
        'minor' => 'Menor',
        'major' => 'Maior',
        'critical' => 'Crítica',
    ];

    public const STATUSES = [
        'open' => 'Aberto',
        'investigating' => 'Em Investigação',
        'corrective_action' => 'Ação Corretiva',
        'closed' => 'Fechado',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function capaRecord(): BelongsTo
    {
        return $this->belongsTo(CapaRecord::class);
    }

    public function qualityAudit(): BelongsTo
    {
        return $this->belongsTo(QualityAudit::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'closed');
    }

    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }
}
