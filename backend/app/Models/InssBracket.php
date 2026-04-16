<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @global Intentionally global */
class InssBracket extends Model
{
    protected $table = 'inss_brackets';

    protected $fillable = [
        'year',
        'min_salary',
        'max_salary',
        'rate',
        'deduction',
    ];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'rate' => 'decimal:2',
            'deduction' => 'decimal:2',
        ];
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
