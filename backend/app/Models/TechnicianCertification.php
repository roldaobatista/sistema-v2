<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TechnicianCertificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property array<int|string, mixed>|null $required_for_service_types
 */
class TechnicianCertification extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TechnicianCertificationFactory> */
    use HasFactory;

    use SoftDeletes;

    const TYPE_CNH = 'cnh';

    const TYPE_NR10 = 'nr10';

    const TYPE_NR11 = 'nr11';

    const TYPE_NR12 = 'nr12';

    const TYPE_NR35 = 'nr35';

    const TYPE_ASO = 'aso';

    const TYPE_TRAINING = 'treinamento';

    const TYPE_CERTIFICATE = 'certificado';

    const STATUS_VALID = 'valid';

    const STATUS_EXPIRING_SOON = 'expiring_soon';

    const STATUS_EXPIRED = 'expired';

    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'name', 'number',
        'issued_at', 'expires_at', 'issuer', 'document_path',
        'status', 'required_for_service_types',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'required_for_service_types' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isFuture()
            && $this->expires_at->diffInDays(now()) <= $days;
    }

    public function isValid(): bool
    {
        if ($this->status === self::STATUS_REVOKED) {
            return false;
        }

        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public function refreshStatus(): void
    {
        if ($this->status === self::STATUS_REVOKED) {
            return;
        }

        if ($this->isExpired()) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        } elseif ($this->isExpiringSoon()) {
            $this->update(['status' => self::STATUS_EXPIRING_SOON]);
        } else {
            $this->update(['status' => self::STATUS_VALID]);
        }
    }
}
