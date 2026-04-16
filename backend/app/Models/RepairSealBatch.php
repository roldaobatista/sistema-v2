<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $received_at
 * @property int|null $quantity
 * @property int|null $quantity_available
 */
class RepairSealBatch extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'type',
        'batch_code',
        'range_start',
        'range_end',
        'prefix',
        'suffix',
        'quantity',
        'quantity_available',
        'supplier',
        'invoice_number',
        'received_at',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'quantity' => 'integer',
            'quantity_available' => 'integer',
        ];

    }

    // ─── Relationships ──────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function seals(): HasMany
    {
        return $this->hasMany(InmetroSeal::class, 'batch_id');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeWithAvailableSeals(Builder $query): Builder
    {
        return $query->where('quantity_available', '>', 0);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getUsagePercentageAttribute(): float
    {
        if ($this->quantity === 0) {
            return 0;
        }

        return round((($this->quantity - $this->quantity_available) / $this->quantity) * 100, 1);
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === InmetroSeal::TYPE_SELO_REPARO ? 'Selo de Reparo' : 'Lacre';
    }

    // ─── Methods ────────────────────────────────────────────

    public function decrementAvailable(int $count = 1): void
    {
        $this->decrement('quantity_available', $count);
    }

    public function incrementAvailable(int $count = 1): void
    {
        $this->increment('quantity_available', $count);
    }
}
