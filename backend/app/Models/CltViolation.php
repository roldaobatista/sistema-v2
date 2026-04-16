<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property bool|null $resolved
 * @property Carbon|null $resolved_at
 * @property array<int|string, mixed>|null $metadata
 */
class CltViolation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'date',
        'violation_type',
        'severity',
        'description',
        'resolved',
        'resolved_at',
        'resolved_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
