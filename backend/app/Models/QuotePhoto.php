<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $path
 */
class QuotePhoto extends Model
{
    use BelongsToTenant, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'tenant_id', 'quote_equipment_id', 'quote_item_id', 'path', 'caption', 'sort_order',
    ];

    /**
     * @return BelongsTo<QuoteEquipment, $this>
     */
    public function quoteEquipment(): BelongsTo
    {
        return $this->belongsTo(QuoteEquipment::class);
    }

    /**
     * @return BelongsTo<QuoteItem, $this>
     */
    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }
}
