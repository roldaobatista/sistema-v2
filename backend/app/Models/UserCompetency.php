<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 */
class UserCompetency extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'equipment_id',
        'supervisor_id',
        'method_name',
        'category',
        'status',
        'issued_at',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
