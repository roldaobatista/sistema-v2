<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool|null $is_main
 * @property numeric-string|null $latitude
 * @property numeric-string|null $longitude
 */
class CustomerAddress extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'type', 'street', 'number',
        'complement', 'district', 'city', 'state', 'zip',
        'country', 'is_main', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
