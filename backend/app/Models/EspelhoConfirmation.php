<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $confirmed_at
 * @property array<int|string, mixed>|null $espelho_snapshot
 * @property array<int|string, mixed>|null $device_info
 */
class EspelhoConfirmation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'year',
        'month',
        'confirmation_hash',
        'confirmed_at',
        'confirmation_method',
        'ip_address',
        'device_info',
        'espelho_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'espelho_snapshot' => 'array',
            'device_info' => 'array',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
