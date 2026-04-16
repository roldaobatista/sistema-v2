<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $ratings
 * @property array<int|string, mixed>|null $okrs
 * @property int|null $year
 * @property int|null $nine_box_potential
 * @property int|null $nine_box_performance
 */
class PerformanceReview extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'reviewer_id',
        'title',
        'cycle',
        'year',
        'type',
        'status',
        'ratings',
        'okrs',
        'nine_box_potential',
        'nine_box_performance',
        'action_plan',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'ratings' => 'array',
            'okrs' => 'array',
            'year' => 'integer',
            'nine_box_potential' => 'integer',
            'nine_box_performance' => 'integer',
        ];

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
