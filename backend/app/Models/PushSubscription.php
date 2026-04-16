<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'endpoint',
        'p256dh_key',
        'auth_key',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
