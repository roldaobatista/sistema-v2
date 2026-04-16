<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @global Intentionally global */
class ProductKit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'parent_id',
        'child_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'child_id');
    }
}
