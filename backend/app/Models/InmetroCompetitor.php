<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $authorized_species
 * @property array<int|string, mixed>|null $mechanics
 * @property array<int|string, mixed>|null $accuracy_classes
 * @property Carbon|null $authorization_valid_until
 * @property Carbon|null $last_repair_date
 * @property int|null $total_repairs_done
 */
class InmetroCompetitor extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'cnpj', 'authorization_number',
        'phone', 'email', 'address', 'city', 'state',
        'authorized_species', 'mechanics',
        'max_capacity', 'accuracy_classes', 'authorization_valid_until',
        'total_repairs_done', 'last_repair_date', 'website',
    ];

    protected function casts(): array
    {
        return [
            'authorized_species' => 'array',
            'mechanics' => 'array',
            'accuracy_classes' => 'array',
            'authorization_valid_until' => 'date',
            'last_repair_date' => 'date',
            'total_repairs_done' => 'integer',
        ];
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(CompetitorInstrumentRepair::class, 'competitor_id');
    }

    public function historyEntries(): HasMany
    {
        return $this->hasMany(InmetroHistory::class, 'competitor_id');
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeByState($query, string $state)
    {
        return $query->where('state', $state);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(InmetroCompetitorSnapshot::class, 'competitor_id');
    }

    public function instruments()
    {
        return $this->belongsToMany(
            InmetroInstrument::class,
            'competitor_instrument_repairs',
            'competitor_id',
            'instrument_id'
        );
    }
}
