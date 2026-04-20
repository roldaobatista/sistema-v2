<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuoteTag extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'color',
    ];

    public function quotes(): BelongsToMany
    {
        $relation = $this->belongsToMany(Quote::class, 'quote_quote_tag')
            ->withPivot('tenant_id');

        return $this->tenant_id ? $relation->withPivotValue('tenant_id', $this->tenant_id) : $relation;
    }
}
