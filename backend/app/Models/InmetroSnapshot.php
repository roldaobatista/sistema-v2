<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $captured_at
 * @property numeric-string|null $market_share_pct
 * @property array<int|string, mixed>|null $by_city
 * @property array<int|string, mixed>|null $by_type
 */
class InmetroSnapshot extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'inmetro_competitor_snapshots';

    protected $fillable = [
        'tenant_id',
        'competitor_id',
        'snapshot_type',
        'period_start',
        'period_end',
        'instrument_count',
        'repair_count',
        'new_instruments',
        'lost_instruments',
        'market_share_pct',
        'by_city',
        'by_type',
        'data',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'captured_at' => 'datetime',
            'market_share_pct' => 'decimal:2',
            'by_city' => 'array',
            'by_type' => 'array',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(InmetroCompetitor::class, 'competitor_id');
    }

    public function getDataAttribute(): array
    {
        return [
            'instrument_count' => (int) ($this->attributes['instrument_count'] ?? 0),
            'repair_count' => (int) ($this->attributes['repair_count'] ?? 0),
            'new_instruments' => (int) ($this->attributes['new_instruments'] ?? 0),
            'lost_instruments' => (int) ($this->attributes['lost_instruments'] ?? 0),
            'market_share_pct' => (float) ($this->attributes['market_share_pct'] ?? 0),
            'by_city' => $this->by_city ?? [],
            'by_type' => $this->by_type ?? [],
        ];
    }

    public function setDataAttribute(array $value): void
    {
        $this->attributes['instrument_count'] = $value['instrument_count'] ?? $value['total_instruments'] ?? 0;
        $this->attributes['repair_count'] = $value['repair_count'] ?? 0;
        $this->attributes['new_instruments'] = $value['new_instruments'] ?? 0;
        $this->attributes['lost_instruments'] = $value['lost_instruments'] ?? 0;
        $this->attributes['market_share_pct'] = $value['market_share_pct'] ?? 0;
        $this->attributes['by_city'] = json_encode($value['by_city'] ?? []);
        $this->attributes['by_type'] = json_encode($value['by_type'] ?? []);
    }
}
