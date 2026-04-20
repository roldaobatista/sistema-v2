<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $old_values
 * @property array<int|string, mixed>|null $new_values
 * @property Carbon|null $created_at
 * @property AuditAction|null $action
 */
class AuditLog extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    /**
     * sec-16 (S2): $fillable restrito a campos de contexto do evento (action,
     * descricao, payload de diff, auditable_*). tenant_id/user_id/ip_address/
     * user_agent/created_at saem fora — sao calculados internamente em
     * AuditLog::log() via forceFill() e nao podem ser fornecidos por payload
     * HTTP ou mass-assignment. Isso fecha o vetor de forjar/backdate evidencia.
     */
    protected $fillable = [
        'action', 'auditable_type', 'auditable_id',
        'description', 'old_values', 'new_values',
    ];

    /**
     * Compatibilidade com payloads legacy que ainda enviam model_type/model_id.
     */
    public function setModelTypeAttribute(?string $value): void
    {
        $this->attributes['auditable_type'] = $value;
    }

    public function getModelTypeAttribute(): ?string
    {
        return $this->attributes['auditable_type'] ?? null;
    }

    public function setModelIdAttribute(?int $value): void
    {
        $this->attributes['auditable_id'] = $value;
    }

    public function getModelIdAttribute(): ?int
    {
        return isset($this->attributes['auditable_id'])
            ? (int) $this->attributes['auditable_id']
            : null;
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'action' => AuditAction::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ponto unico e legitimo de escrita em audit_logs.
     *
     * Todos os campos sensiveis (tenant_id, user_id, ip_address, user_agent,
     * created_at) sao resolvidos internamente via forceFill() — nao sao
     * aceitos por mass-assignment (sec-16). Isso garante:
     *  - tenant_id sempre preenchido (sec-09: fallback 0 se nao resolver)
     *  - user_id sempre preenchido (sec-25: fallback 0 em jobs/console)
     *  - user_agent truncado/sanitizado (sec-14: anti log-injection)
     *  - description generica (sec-10: chamador deve omitir PII)
     */
    public static function log(AuditAction|string $action, string $description, ?Model $model = null, ?array $old = null, ?array $new = null): self
    {
        $actionEnum = $action instanceof AuditAction ? $action : (AuditAction::tryFrom($action) ?? AuditAction::UPDATED);

        $log = new self;
        $log->forceFill([
            'tenant_id' => self::resolveTenantId($model),
            'user_id' => self::resolveUserId(),
            'action' => $actionEnum->value,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => self::sanitizeUserAgent(request()->userAgent()),
            'created_at' => now(),
        ]);
        $log->save();

        return $log;
    }

    /**
     * sec-09 (S2): tenant_id NUNCA pode ser NULL. Fallback para 0 ("system") quando
     * nao ha contexto — preserva FK contra tenants.id=0 (seeder garante row system).
     * Registros com tenant_id=0 sao consultaveis via AuditLog::withoutGlobalScopes()
     * ->where('tenant_id', 0).
     */
    private static function resolveTenantId(?Model $model): int
    {
        if ($model && array_key_exists('tenant_id', $model->getAttributes())) {
            $tenantId = (int) $model->getAttribute('tenant_id');
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        if ($model instanceof Tenant) {
            return (int) $model->getKey();
        }

        if (app()->bound('current_tenant_id')) {
            $bound = (int) app('current_tenant_id');
            if ($bound > 0) {
                return $bound;
            }
        }

        $user = auth()->user();
        if ($user) {
            $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id ?? 0);
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        return 0; // system — nao existe tenant real para associar.
    }

    /**
     * sec-25 (S2): user_id NUNCA deve ser NULL. Jobs/console/webhooks sem
     * usuario autenticado caem em 0 ("system"), preservando rastreabilidade
     * binaria (usuario vs automatico).
     */
    private static function resolveUserId(): int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : 0;
    }

    /**
     * sec-14 (S3): trunca user_agent para 255 chars e remove caracteres de
     * controle (\n, \r, \0, etc.) — fecha vetor de log-injection onde um
     * atacante com UA malicioso insere novas "linhas" no audit (CWE-117).
     */
    private static function sanitizeUserAgent(?string $ua): ?string
    {
        if ($ua === null) {
            return null;
        }

        // Substitui qualquer caractere de controle ASCII (0x00-0x1F, 0x7F) por espaco.
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $ua) ?? $ua;

        // Truncamento mb-safe para 255 chars (coluna VARCHAR(255)).
        if (mb_strlen($clean) > 255) {
            $clean = mb_substr($clean, 0, 255);
        }

        return $clean;
    }
}
