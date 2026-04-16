<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // ── Constantes de nomes de roles do sistema ──
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const GERENTE = 'gerente';

    public const COORDENADOR = 'coordenador';

    public const TECNICO = 'tecnico';

    public const FINANCEIRO = 'financeiro';

    public const COMERCIAL = 'comercial';

    public const VENDEDOR = 'vendedor';

    public const TECNICO_VENDEDOR = 'tecnico_vendedor';

    public const ATENDIMENTO = 'atendimento';

    public const RH = 'rh';

    public const ESTOQUISTA = 'estoquista';

    public const QUALIDADE = 'qualidade';

    public const VISUALIZADOR = 'visualizador';

    public const MOTORISTA = 'motorista';

    public const MONITOR = 'monitor';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'guard_name',
        'tenant_id',
    ];

    /**
     * Get the tenant that owns the role.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Explicit users relationship for withCount support.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }

    /**
     * Override Spatie create to bypass simple name+guard uniqueness check.
     * We rely on database unique constraint (name, guard, tenant_id).
     */
    public static function create(array $attributes = [])
    {
        // Garante guard_name
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        // Auto-assign tenant_id from app context if not explicitly provided
        if (! array_key_exists('tenant_id', $attributes) && app()->bound('current_tenant_id')) {
            $attributes['tenant_id'] = app('current_tenant_id');
        }

        return static::query()->create($attributes);
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('team_id', $attributes)) {
            throw new MassAssignmentException('team_id is not mass assignable.');
        }

        return parent::fill($attributes);
    }
}
