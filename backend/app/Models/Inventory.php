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
 * @property int|null $warehouse_id
 * @property int|null $created_by
 * @property string|null $reference
 * @property string|null $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse|null $warehouse
 * @property-read User|null $creator
 * @property-read Collection<int, InventoryItem> $items
 */
class Inventory extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    const STATUS_OPEN = 'open';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'reference',
        'status',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
