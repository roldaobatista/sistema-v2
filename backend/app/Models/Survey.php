<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $questions
 * @property bool|null $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Survey extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'title', 'description', 'status', 'created_by', 'starts_at', 'ends_at', 'is_active', 'questions',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
