<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property int|null $pipeline_id
 * @property int|null $stage_id
 * @property string $title
 * @property float|null $value
 * @property int|null $probability
 * @property Carbon|null $expected_close_date
 * @property string|null $source
 * @property int|null $assigned_to
 * @property int|null $quote_id
 * @property int|null $work_order_id
 * @property int|null $equipment_id
 * @property string $status
 * @property Carbon|null $won_at
 * @property Carbon|null $lost_at
 * @property string|null $lost_reason
 * @property int|null $loss_reason_id
 * @property string|null $competitor_name
 * @property float|null $competitor_price
 * @property float|null $score
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer|null $customer
 * @property-read CrmPipeline|null $pipeline
 * @property-read CrmPipelineStage|null $stage
 * @property-read User|null $assignee
 * @property-read Quote|null $quote
 * @property-read WorkOrder|null $workOrder
 * @property-read Equipment|null $equipment
 * @property-read Collection<int, CrmActivity> $activities
 * @property-read Collection<int, CrmDealProduct> $products
 * @property-read Collection<int, AgendaItem> $followUpTasks
 * @property-read Collection<int, CrmDealStageHistory> $stageHistory
 */
class CrmDeal extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'pipeline_id', 'stage_id',
        'title', 'value', 'probability', 'expected_close_date',
        'source', 'assigned_to', 'quote_id', 'work_order_id',
        'equipment_id', 'status', 'won_at', 'lost_at',
        'lost_reason', 'loss_reason_id', 'competitor_name', 'competitor_price',
        'score', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'probability' => 'integer',
            'score' => 'decimal:2',
            'competitor_price' => 'decimal:2',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public const STATUS_OPEN = 'open';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_OPEN => ['label' => 'Aberto', 'color' => 'info'],
        self::STATUS_WON => ['label' => 'Ganho', 'color' => 'success'],
        self::STATUS_LOST => ['label' => 'Perdido', 'color' => 'danger'],
    ];

    public const SOURCES = [
        'calibracao_vencendo' => 'Calibração Vencendo',
        'indicacao' => 'Indicação',
        'prospeccao' => 'Prospecção',
        'chamado' => 'Chamado Técnico',
        'contrato_renovacao' => 'Renovação de Contrato',
        'retorno' => 'Retorno de Cliente',
        'outro' => 'Outro',
    ];

    // ─── Scopes ─────────────────────────────────────────

    public function scopeOpen($q)
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopeWon($q)
    {
        return $q->where('status', self::STATUS_WON);
    }

    public function scopeLost($q)
    {
        return $q->where('status', self::STATUS_LOST);
    }

    public function scopeByPipeline($q, int $pipelineId)
    {
        return $q->where('pipeline_id', $pipelineId);
    }

    // ─── Methods ────────────────────────────────────────

    public function markAsWon(): void
    {
        if ($this->status === self::STATUS_WON) {
            return;
        }

        $wonStage = $this->pipeline->stages()->wonStage()->first();

        $this->update([
            'status' => self::STATUS_WON,
            'probability' => 100,
            'won_at' => now(),
            'stage_id' => $wonStage?->id ?? $this->stage_id,
        ]);
    }

    public function markAsLost(?string $reason = null): void
    {
        if ($this->status === self::STATUS_LOST) {
            return;
        }

        $lostStage = $this->pipeline->stages()->lostStage()->first();

        $this->update([
            'status' => self::STATUS_LOST,
            'probability' => 0,
            'lost_at' => now(),
            'lost_reason' => $reason,
            'stage_id' => $lostStage?->id ?? $this->stage_id,
        ]);
    }

    public function moveToStage(int $stageId): void
    {
        if ($this->status !== self::STATUS_OPEN) {
            throw new \DomainException("Não é possível mover um deal com status '{$this->status}'. Apenas deals abertos podem ser movidos entre estágios.");
        }

        $stage = CrmPipelineStage::findOrFail($stageId);

        if ($stage->pipeline_id !== $this->pipeline_id) {
            throw new \DomainException('O estágio de destino não pertence ao mesmo pipeline do deal.');
        }

        $this->update([
            'stage_id' => $stageId,
            'probability' => $stage->probability,
        ]);
    }

    // ─── Relationships ──────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function lossReason(): BelongsTo
    {
        return $this->belongsTo(CrmLossReason::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'deal_id')->orderByDesc('created_at');
    }

    public function products(): HasMany
    {
        return $this->hasMany(CrmDealProduct::class, 'deal_id');
    }

    public function followUpTasks(): HasMany
    {
        return $this->hasMany(CrmFollowUpTask::class, 'deal_id');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(CrmDealStageHistory::class, 'deal_id');
    }
}
