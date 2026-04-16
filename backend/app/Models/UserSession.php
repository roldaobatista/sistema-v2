<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @global Intentionally global */
class UserSession extends Model
{
    protected $fillable = [
        'user_id', 'token_id', 'ip_address', 'user_agent',
        'last_activity',
    ];

    protected function casts(): array
    {
        return [
            'last_activity' => 'datetime',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
