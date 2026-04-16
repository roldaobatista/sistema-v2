<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $total_steps
 */
class CrmSequence extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'crm_sequences';

    protected $fillable = [
        'tenant_id', 'name', 'description',
        'status', 'total_steps', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_steps' => 'integer',
        ];
    }

    public const STATUSES = [
        'active' => 'Ativa',
        'paused' => 'Pausada',
        'archived' => 'Arquivada',
    ];

    // ─── Scopes ─────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    // ─── Relationships ──────────────────────────────────

    /** @return HasMany<CrmSequenceStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(CrmSequenceStep::class, 'sequence_id')->orderBy('step_order');
    }

    /** @return HasMany<CrmSequenceEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CrmSequenceEnrollment::class, 'sequence_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
