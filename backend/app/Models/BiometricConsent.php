<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BiometricConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $consented_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $retention_days
 * @property bool|null $is_active
 */
class BiometricConsent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BiometricConsentFactory> */
    use HasFactory;

    const TYPE_GEOLOCATION = 'geolocation';

    const TYPE_FACIAL = 'facial';

    const TYPE_FINGERPRINT = 'fingerprint';

    const TYPE_VOICE = 'voice';

    const BASIS_CONSENT = 'consent';

    const BASIS_LEGITIMATE_INTEREST = 'legitimate_interest';

    const BASIS_LEGAL_OBLIGATION = 'legal_obligation';

    protected $fillable = [
        'tenant_id', 'user_id', 'data_type', 'legal_basis',
        'purpose', 'consented_at', 'expires_at', 'revoked_at',
        'alternative_method', 'retention_days', 'is_active',
    ];

    /**
     * Campos sensiveis sob LGPD Art. 11 (dado pessoal sensivel biometrico).
     * Permanecem encriptados no DB via $casts e omitidos por default em
     * toArray()/toJson(). Endpoints que legitimamente precisem retornar
     * devem invocar makeVisible([...]) explicitamente.
     *
     * @var list<string>
     */
    protected $hidden = [
        'purpose',
        'alternative_method',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => 'encrypted',
            'alternative_method' => 'encrypted',
            'consented_at' => 'date',
            'expires_at' => 'date',
            'revoked_at' => 'date',
            'retention_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->revoked_at) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function revoke(): void
    {
        $this->update([
            'revoked_at' => now(),
            'is_active' => false,
        ]);
    }
}
