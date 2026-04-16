<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCallComment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'service_call_id',
        'user_id',
        'content',
    ];

    public function serviceCall(): BelongsTo
    {
        return $this->belongsTo(ServiceCall::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
