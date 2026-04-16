<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @global Intentionally global */
class MinimumWage extends Model
{
    protected $table = 'minimum_wages';

    protected $fillable = [
        'year',
        'month',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('year', now()->year)
            ->where('month', now()->month);
    }
}
