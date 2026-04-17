<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @global Intentionally global */
class AssetTagScan extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'asset_tag_id', 'scanned_by', 'action', 'location', 'latitude', 'longitude', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];

    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(AssetTag::class, 'asset_tag_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
