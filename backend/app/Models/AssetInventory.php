<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AssetInventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $inventory_date
 * @property bool|null $condition_ok
 * @property bool|null $divergent
 * @property bool|null $synced_from_pwa
 */
class AssetInventory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AssetInventoryFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'asset_record_id',
        'inventory_date',
        'counted_location',
        'counted_status',
        'condition_ok',
        'divergent',
        'offline_reference',
        'synced_from_pwa',
        'notes',
        'counted_by',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date',
            'condition_ok' => 'boolean',
            'divergent' => 'boolean',
            'synced_from_pwa' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AssetRecord, $this>
     */
    public function assetRecord(): BelongsTo
    {
        return $this->belongsTo(AssetRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
