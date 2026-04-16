<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<int|string, mixed>|null $conditions
 * @property array<int|string, mixed>|null $actions
 * @property bool|null $is_active
 */
class CrmFunnelAutomation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'pipeline_id',
        'stage_id',
        'name',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CrmPipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    /**
     * @return BelongsTo<CrmPipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
