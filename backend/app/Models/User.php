<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasEncryptedSearchableField;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * @use HasFactory<UserFactory>
 *
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property bool|null $is_active
 * @property float|null $location_lat
 * @property float|null $location_lng
 * @property Carbon|null $location_updated_at
 * @property array<int|string, mixed>|null $denied_permissions
 * @property Carbon|null $google_calendar_synced_at
 * @property Carbon|null $admission_date
 * @property Carbon|null $termination_date
 * @property Carbon|null $birth_date
 * @property numeric-string|null $salary
 * @property int|null $dependents_count
 */
class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasEncryptedSearchableField, HasFactory, Notifiable, SoftDeletes;
    use HasRoles {
        hasPermissionTo as spatieHasPermissionTo;
        assignRole as spatieAssignRole;
    }

    protected string $guard_name = 'web';

    /**
     * Campos encrypted que precisam de coluna *_hash para busca determinística.
     *
     * @var array<string, string>
     */
    protected array $encryptedSearchableFields = [
        'cpf' => 'cpf_hash',
    ];

    /**
     * Campos mass-assignable.
     *
     * SEC-08 (Re-auditoria Camada 1, 2026-04-19): `is_active`,
     * `current_tenant_id` e `denied_permissions` estão deliberadamente FORA
     * do $fillable — expô-los permitiria escalonamento de privilégio,
     * sequestro de tenant e bypass de denylist via body de qualquer endpoint
     * que faça `User::create($request->validated())` ou `$user->update($v)`.
     * Paths administrativos legítimos (login/switchTenant/toggleActive/
     * syncDeniedPermissions) atribuem esses campos via `forceFill()->save()`.
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'password',
        'branch_id',
        'last_login_at',
        'location_lat',
        'location_lng',
        'location_updated_at',
        'status',
        'google_calendar_token',
        'google_calendar_refresh_token',
        'google_calendar_email',
        'google_calendar_synced_at',
        // Labor compliance fields
        'pis_number',
        'cpf',
        'cpf_hash',
        'ctps_number',
        'ctps_series',
        'admission_date',
        'termination_date',
        'salary',
        'salary_type',
        'work_shift',
        'journey_rule_id',
        'cbo_code',
        'birth_date',
        'gender',
        'marital_status',
        'education_level',
        'nationality',
        'rg_number',
        'rg_issuer',
        'voter_title',
        'military_cert',
        'bank_code',
        'bank_agency',
        'bank_account',
        'bank_account_type',
        'dependents_count',
    ];

    /**
     * SEC-021 (Audit Camada 1, Wave 1D): `cpf_hash` é hash determinístico
     * (HMAC-SHA256 com APP_KEY) usado para busca em `cpf` encrypted. Expor
     * publicamente facilita ataque de dicionário offline contra o CPF —
     * domínio enumerável (~10^11). Ocultar de toArray()/toJson() preserva o
     * benefício de busca interna sem vazamento da prova de existência do PII.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_calendar_token',
        'google_calendar_refresh_token',
        'cpf_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'location_lat' => 'float',
            'location_lng' => 'float',
            'location_updated_at' => 'datetime',
            'denied_permissions' => 'array',
            'google_calendar_token' => 'encrypted',
            'google_calendar_refresh_token' => 'encrypted',
            'google_calendar_synced_at' => 'datetime',
            'admission_date' => 'date',
            'termination_date' => 'date',
            'birth_date' => 'date',
            'salary' => 'decimal:2',
            'dependents_count' => 'integer',
            'cpf' => 'encrypted',
        ];
    }

    /**
     * Returns the list of explicitly denied permissions for this user.
     */
    public function getDeniedPermissionsList(): array
    {
        return $this->denied_permissions ?? [];
    }

    /**
     * Check if a specific permission is denied for this user.
     */
    public function isPermissionDenied(string $permission): bool
    {
        return in_array($permission, $this->getDeniedPermissionsList(), true);
    }

    /**
     * Get effective permissions: all granted minus denied.
     */
    public function getEffectivePermissions(): Collection
    {
        $denied = $this->getDeniedPermissionsList();

        return $this->getAllPermissions()
            ->filter(fn ($perm) => ! in_array($perm->name, $denied, true));
    }

    // Relacionamentos

    /**
     * Resolve o bug do Spatie v6 ao usar Global Roles com Teams habilitados.
     * O Spatie falha na validação do pivot se a Role for global mas assinada via Team.
     * Aplicamos fallback confiável usando a listagem completa resolvida.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        try {
            if ($this->spatieHasPermissionTo($permission, $guardName)) {
                return true;
            }
        } catch (PermissionDoesNotExist $e) {
            return false;
        }

        $permissionName = is_string($permission) ? $permission : (is_int($permission) ? null : $permission->name);

        if (! $permissionName) {
            return false;
        }

        return $this->getAllPermissions()->pluck('name')->contains($permissionName);
    }

    public function assignRole(...$roles)
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $previousTeamId = $permissionRegistrar->getPermissionsTeamId();
        $resolvedTeamId = $this->current_tenant_id ?? $this->tenant_id ?? $previousTeamId;

        if ($resolvedTeamId !== null) {
            setPermissionsTeamId($resolvedTeamId);
        }

        try {
            return $this->spatieAssignRole(...$roles);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /** @return BelongsTo<JourneyRule, $this> */
    public function journeyRule(): BelongsTo
    {
        return $this->belongsTo(JourneyRule::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return HasMany<User, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** @return HasMany<UserSkill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }

    /** @return HasMany<TechnicianSkill, $this> */
    public function technicianSkills(): HasMany
    {
        return $this->hasMany(TechnicianSkill::class);
    }

    /** @return HasMany<PerformanceReview, $this> */
    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'user_id'); // as reviewee
    }

    /** @return HasMany<PerformanceReview, $this> */
    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'reviewer_id');
    }

    /** @return HasMany<ContinuousFeedback, $this> */
    public function receivedFeedback(): HasMany
    {
        return $this->hasMany(ContinuousFeedback::class, 'to_user_id');
    }

    /** @return HasMany<ContinuousFeedback, $this> */
    public function sentFeedback(): HasMany
    {
        return $this->hasMany(ContinuousFeedback::class, 'from_user_id');
    }

    /** @return HasMany<TimeClockEntry, $this> */
    public function timeClockEntries(): HasMany
    {
        return $this->hasMany(TimeClockEntry::class);
    }

    /** @return HasOne<TwoFactorAuth, $this> */
    public function twoFactorAuth(): HasOne
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function hasTenantAccess(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        if ((int) $this->tenant_id === $tenantId) {
            return true;
        }

        return $this->tenants()->where('tenants.id', $tenantId)->exists();
    }

    public function switchTenant(Tenant|int $tenant): self
    {
        $tenantId = $tenant instanceof Tenant ? (int) $tenant->id : (int) $tenant;

        if ($tenantId <= 0 || ! $this->hasTenantAccess($tenantId)) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
        }

        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenantId);

        if ($tenantModel->isInactive()) {
            throw new \InvalidArgumentException('Não é possível trocar para uma empresa inativa.');
        }

        $previousTenantId = $this->current_tenant_id;
        $this->forceFill(['current_tenant_id' => $tenantId])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($previousTenantId !== $tenantId) {
            app()->instance('current_tenant_id', $tenantId);
            AuditLog::log(
                'tenant_switch',
                "Usuário {$this->name} trocou de empresa: #{$previousTenantId} → #{$tenantId} ({$tenantModel->name})",
                $tenantModel
            );
        }

        return $this->refresh();
    }

    /** @return HasMany<AgendaItem, $this> */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class, 'user_id');
    }

    /** @return HasMany<ServiceCall, $this> */
    public function serviceCalls(): HasMany
    {
        return $this->hasMany(ServiceCall::class, 'technician_id');
    }

    // ── HR Relationships ──

    /** @return HasMany<PayrollLine, $this> */
    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /** @return HasManyThrough<Payslip, PayrollLine, $this> */
    public function payslips(): HasManyThrough
    {
        return $this->hasManyThrough(Payslip::class, PayrollLine::class);
    }

    /** @return HasOne<Rescission, $this> */
    public function rescission(): HasOne
    {
        return $this->hasOne(Rescission::class);
    }

    /** @return HasMany<HourBankTransaction, $this> */
    public function hourBankTransactions(): HasMany
    {
        return $this->hasMany(HourBankTransaction::class);
    }

    /** @return HasMany<VacationBalance, $this> */
    public function vacationBalances(): HasMany
    {
        return $this->hasMany(VacationBalance::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** @return HasMany<EmployeeDocument, $this> */
    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /** @return HasMany<EmployeeDependent, $this> */
    public function employeeDependents(): HasMany
    {
        return $this->hasMany(EmployeeDependent::class);
    }

    /** @return HasMany<EmployeeBenefit, $this> */
    public function employeeBenefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    /** @return HasMany<JourneyEntry, $this> */
    public function journeyEntries(): HasMany
    {
        return $this->hasMany(JourneyEntry::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function approvedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    /** @return HasMany<TimeClockAdjustment, $this> */
    public function approvedClockAdjustments(): HasMany
    {
        return $this->hasMany(TimeClockAdjustment::class, 'approved_by');
    }

    /** @return HasMany<Payroll, $this> */
    public function calculatedPayrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'calculated_by');
    }
}
