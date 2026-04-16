<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AssetMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $moved_at
 */
class AssetMovement extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AssetMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'asset_record_id',
        'movement_type',
        'from_location',
        'to_location',
        'from_responsible_user_id',
        'to_responsible_user_id',
        'moved_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromResponsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_responsible_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toResponsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_responsible_user_id');
    }
}
