<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int|null $total_score
 * @property int|null $expiration_score
 * @property int|null $value_score
 * @property int|null $contact_score
 * @property int|null $region_score
 * @property int|null $instrument_score
 * @property array<int|string, mixed>|null $factors
 * @property Carbon|null $calculated_at
 */
class InmetroLeadScore extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'owner_id', 'tenant_id', 'total_score', 'expiration_score',
        'value_score', 'contact_score', 'region_score', 'instrument_score',
        'factors', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'integer',
            'expiration_score' => 'integer',
            'value_score' => 'integer',
            'contact_score' => 'integer',
            'region_score' => 'integer',
            'instrument_score' => 'integer',
            'factors' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(InmetroOwner::class, 'owner_id');
    }
}
