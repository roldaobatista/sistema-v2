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

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
        'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
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

    public static function log(AuditAction|string $action, string $description, ?Model $model = null, ?array $old = null, ?array $new = null): static
    {
        $actionEnum = $action instanceof AuditAction ? $action : (AuditAction::tryFrom($action) ?? AuditAction::UPDATED);

        return static::create([
            'tenant_id' => self::resolveTenantId($model),
            'user_id' => auth()->id(),
            'action' => $actionEnum,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private static function resolveTenantId(?Model $model): ?int
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
            return (int) app('current_tenant_id');
        }

        $user = auth()->user();
        if ($user) {
            $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id ?? 0);
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        return null;
    }
}
