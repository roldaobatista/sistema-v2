<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property bool|null $is_active
 */
class ClientPortalUser extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        // Hardening (estrutura pronta — lógica de lockout/2FA/password-history
        // será ativada em sprint dedicada de portal security; ver
        // docs/TECHNICAL-DECISIONS.md §14.6).
        'failed_login_attempts',
        'locked_until',
        'password_changed_at',
        'password_history',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Sensíveis — nunca devem aparecer em respostas/serialização.
        'two_factor_secret',
        'two_factor_recovery_codes',
        'password_history',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'password_history' => 'array',
            'two_factor_enabled' => 'boolean',
            // 2FA secret e recovery codes criptografados em repouso.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];

    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getCurrentTenantIdAttribute(): ?int
    {
        return (int) $this->tenant_id;
    }
}
