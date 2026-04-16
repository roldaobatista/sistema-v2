<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool|null $is_won
 * @property bool|null $is_lost
 */
class CrmPipelineStage extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $stage): void {
            if (array_key_exists('order', $stage->getAttributes())) {
                $stage->attributes['sort_order'] = $stage->attributes['order'];
                unset($stage->attributes['order']);
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'pipeline_id', 'name', 'color', 'sort_order',
        'probability', 'is_won', 'is_lost', 'order',
    ];

    protected function casts(): array
    {
        return [
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public function getOrderAttribute(): int
    {
        return (int) ($this->attributes['sort_order'] ?? 0);
    }

    public function setOrderAttribute(int $value): void
    {
        $this->attributes['sort_order'] = $value;
    }

    public function scopeWonStage($q)
    {
        return $q->where('is_won', true);
    }

    public function scopeLostStage($q)
    {
        return $q->where('is_lost', true);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'stage_id');
    }
}
