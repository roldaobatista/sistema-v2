<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\Auditable;
use App\Observers\TenantObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(TenantObserver::class)]
/** @global Intentionally global */
class Tenant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'tenants';

    // Constantes mantidas para compatibilidade retroativa com factories e seeds
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_TRIAL = 'trial';

    protected $fillable = [
        'name',
        'trade_name',
        'document',
        'email',
        'phone',
        'status',
        'website',
        'slug',
        'is_active',
        'signing_key',
        'state_registration',
        'city_registration',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'address_zip',
        'inmetro_config',
        'logo_path',
        // Fiscal config
        'fiscal_regime',
        'cnae_code',
        'fiscal_certificate_path',
        'fiscal_certificate_password',
        'fiscal_certificate_expires_at',
        'fiscal_nfse_token',
        'fiscal_nfse_city',
        'fiscal_nfe_series',
        'fiscal_nfe_next_number',
        'fiscal_nfse_rps_series',
        'fiscal_nfse_rps_next_number',
        'fiscal_environment',
        'rep_p_program_name',
        'rep_p_version',
        'rep_p_developer_name',
        'rep_p_developer_cnpj',
        'timezone',
        'current_plan_id',
    ];

    protected $hidden = [
        'fiscal_certificate_password',
        'fiscal_nfse_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'inmetro_config' => 'array',
            'fiscal_regime' => 'integer',
            'fiscal_nfe_series' => 'integer',
            'fiscal_nfe_next_number' => 'integer',
            'fiscal_nfse_rps_next_number' => 'integer',
            'fiscal_certificate_expires_at' => 'date',
            'fiscal_certificate_password' => 'encrypted',
            'fiscal_nfse_token' => 'encrypted',
        ];
    }

    /* ── Relationships ── */

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tenants')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function numberingSequences(): HasMany
    {
        return $this->hasMany(NumberingSequence::class);
    }

    public function fiscalNotes(): HasMany
    {
        return $this->hasMany(FiscalNote::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return BelongsTo<SaasPlan, $this>
     */
    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'current_plan_id');
    }

    /**
     * @return HasMany<SaasSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    public function activeSubscription(): ?SaasSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', [SaasSubscription::STATUS_ACTIVE, SaasSubscription::STATUS_TRIAL])
            ->latest('started_at')
            ->first();
    }

    /* ── Status Helpers ── */

    public function isActive(): bool
    {
        return $this->status === TenantStatus::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === TenantStatus::INACTIVE;
    }

    public function isTrial(): bool
    {
        return $this->status === TenantStatus::TRIAL;
    }

    public function isAccessible(): bool
    {
        return $this->status !== TenantStatus::INACTIVE;
    }

    /* ── Accessors & Helpers ── */

    public function toLocalTime($utc)
    {
        return Carbon::parse($utc)->setTimezone($this->timezone ?? 'America/Sao_Paulo');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?: $this->name;
    }

    public function getLogoPathAttribute($value): ?string
    {
        return $value ? url(Storage::url($value)) : null;
    }

    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_street,
            $this->address_number ? "nº {$this->address_number}" : null,
            $this->address_complement,
            $this->address_neighborhood,
            $this->address_city ? "{$this->address_city}/{$this->address_state}" : null,
        ]);

        if (empty($parts)) {
            return null;
        }

        $address = implode(', ', $parts);
        if ($this->address_zip) {
            $address .= " — CEP {$this->address_zip}";
        }

        return $address;
    }

    public function getStatusLabelAttribute(): string
    {
        $status = $this->status instanceof TenantStatus ? $this->status : TenantStatus::tryFrom($this->status);

        return $status?->label() ?? (string) $this->status;
    }
}
