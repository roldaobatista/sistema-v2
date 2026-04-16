<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @global Intentionally global */
class IrrfBracket extends Model
{
    protected $table = 'irrf_brackets';

    protected $fillable = [
        'year',
        'min_base',
        'max_base',
        'rate',
        'deduction',
    ];

    protected function casts(): array
    {
        return [
            'min_base' => 'decimal:2',
            'max_base' => 'decimal:2',
            'rate' => 'decimal:2',
            'deduction' => 'decimal:2',
        ];
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
