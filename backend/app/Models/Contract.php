<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool|null $is_active
 */
class Contract extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'number', 'name',
        'description', 'status', 'start_date', 'end_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceCalls(): HasMany
    {
        return $this->hasMany(ServiceCall::class);
    }
}
