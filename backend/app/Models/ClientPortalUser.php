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
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 */
class ClientPortalUser extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    // sec-18 + data-02 (Re-auditoria Camada 1 r3/r4): $fillable restrito a
    // campos de perfil do usuário do portal. Fora do $fillable por regra:
    //  - tenant_id (BelongsToTenant injeta via `creating`)
    //  - is_active (atribuído por lifecycle de ativação/desativação interno)
    //  - two_factor_enabled (virado apenas pelo fluxo de confirmação 2FA)
    //  - last_login_at (stamp automático pós-login bem-sucedido)
    //  - campos de hardening (lockout, 2FA secrets, password_history) —
    //    atribuídos exclusivamente via forceFill() por controllers/jobs
    //    internos de autenticação, nunca por payload HTTP. Fecha vetores
    //    de forjar locked_until/confirmed_at, reativar conta bloqueada,
    //    bypass 2FA e cross-tenant via mass assignment.
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'password',
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
