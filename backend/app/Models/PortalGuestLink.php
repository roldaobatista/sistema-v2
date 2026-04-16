<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon|null $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $used_at
 * @property bool|null $single_use
 */
class PortalGuestLink extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'token',
        'entity_type',
        'entity_id',
        'expires_at',
        'single_use',
        'consumed_at',
        'used_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'used_at' => 'datetime',
            'single_use' => 'boolean',
        ];

    }

    /**
     * Resource that this guest link points to (Quote, WorkOrder, CalibrationCertificate, etc).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the token is still valid (not used, not expired).
     */
    public function isValid(): bool
    {
        if ($this->single_use && ($this->consumed_at !== null || $this->used_at !== null)) {
            return false;
        }

        if ($this->expires_at?->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Consume the token making it invalid for future uses.
     */
    public function consume(): void
    {
        $timestamp = now();
        $payload = ['used_at' => $timestamp];

        if ($this->single_use) {
            $payload['consumed_at'] = $timestamp;
        }

        $this->update($payload);
    }

    /**
     * Generate a brand new token securely.
     */
    public static function generateSecureToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('token', $token)->exists());

        return $token;
    }
}
