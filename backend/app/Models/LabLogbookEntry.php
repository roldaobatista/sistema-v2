<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $temperature
 * @property numeric-string|null $humidity
 * @property Carbon|null $entry_date
 */
class LabLogbookEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'type',
        'description', 'temperature', 'humidity',
        'entry_date',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'entry_date' => 'date',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
