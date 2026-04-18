<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $is_enabled
 * @property Carbon|null $verified_at
 */
class TwoFactorAuth extends Model
{
    use BelongsToTenant;

    protected $table = 'user_2fa';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'secret', 'method', 'is_enabled', 'verified_at',
        'backup_codes', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'verified_at' => 'datetime',
            'secret' => 'encrypted',
            'backup_codes' => 'encrypted:array',
        ];

    }

    protected $hidden = ['secret', 'backup_codes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
