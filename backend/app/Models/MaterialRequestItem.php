<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @global Intentionally global */
class MaterialRequestItem extends Model
{
    protected $fillable = [
        'material_request_id', 'product_id', 'quantity_requested', 'quantity_fulfilled', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:2',
            'quantity_fulfilled' => 'decimal:2',
        ];

    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
