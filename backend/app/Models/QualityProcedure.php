<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\QualityProcedureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $approved_at
 * @property Carbon|null $next_review_date
 * @property int|null $revision
 */
class QualityProcedure extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<QualityProcedureFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'code', 'title', 'description', 'revision', 'category',
        'approved_by', 'approved_at', 'next_review_date', 'status', 'content',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'date',
            'next_review_date' => 'date',
            'revision' => 'integer',
        ];

    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class, 'sourceable_id')
            ->where('sourceable_type', self::class);
    }
}
