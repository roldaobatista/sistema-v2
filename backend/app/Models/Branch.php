<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $code
 * @property string|null $address_street
 * @property string|null $address_number
 * @property string|null $address_complement
 * @property string|null $address_neighborhood
 * @property string|null $address_city
 * @property string|null $address_state
 * @property string|null $address_zip
 * @property string|null $phone
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, NumberingSequence> $numberingSequences
 */
class Branch extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'address_zip',
        'phone',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function numberingSequences(): HasMany
    {
        return $this->hasMany(NumberingSequence::class);
    }

    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_street,
            $this->address_number,
            $this->address_neighborhood,
            $this->address_city ? "{$this->address_city}/{$this->address_state}" : null,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }
}
