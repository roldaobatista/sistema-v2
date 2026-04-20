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

    // sec-18 (Re-auditoria Camada 1 r3): $fillable restrito a campos de
    // perfil do usuário do portal. Campos de hardening (lockout, 2FA,
    // password_history) saem fora — são atribuídos exclusivamente via
    // forceFill() por controllers/jobs internos de autenticação, nunca
    // por payload HTTP. Fecha vetor de forjar locked_until/confirmed_at.
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'two_factor_enabled',
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
