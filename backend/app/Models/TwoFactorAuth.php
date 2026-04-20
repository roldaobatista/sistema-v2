<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

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
            'backup_codes' => 'array',
        ];

    }

    protected $hidden = ['secret', 'backup_codes'];

    /**
     * sec-19 (Re-auditoria Camada 1 r3): mutator garante hash-at-rest para
     * TODOS os backup codes, independente do caller. Idempotente — códigos
     * já hasheados (começam com `$2y$` do bcrypt) passam sem re-hash;
     * plaintext é hasheado. Fecha vetor de caller que esquece Hash::make().
     *
     * @param  array<int, string>|null  $value
     */
    public function setBackupCodesAttribute(?array $value): void
    {
        if ($value === null) {
            $this->attributes['backup_codes'] = null;

            return;
        }

        $hashed = array_map(
            fn (string $code): string => str_starts_with($code, '$2y$')
                ? $code
                : Hash::make($code),
            $value,
        );

        $this->attributes['backup_codes'] = json_encode($hashed);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
