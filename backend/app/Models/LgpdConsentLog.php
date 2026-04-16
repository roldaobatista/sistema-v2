<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 */
class LgpdConsentLog extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'holder_type', 'holder_id', 'holder_name',
        'holder_email', 'holder_document', 'purpose', 'legal_basis',
        'status', 'granted_at', 'revoked_at', 'ip_address',
        'user_agent', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function revoke(string $reason): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }
}
