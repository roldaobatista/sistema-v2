<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expiry_date
 * @property int|null $file_size
 */
class CustomerDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'title', 'type', 'file_path',
        'file_name', 'file_size', 'expiry_date', 'notes', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
