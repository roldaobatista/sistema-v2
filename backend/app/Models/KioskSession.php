<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $allowed_pages
 * @property Carbon|null $started_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $ended_at
 */
class KioskSession extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'kiosk_sessions';

    public const STATUSES = ['active', 'idle', 'expired', 'closed'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'device_identifier',
        'status',
        'allowed_pages',
        'started_at',
        'last_activity_at',
        'ended_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'allowed_pages' => 'array',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
        ];

    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_identifier', $deviceId);
    }

    // ── Helpers ──

    public function recordActivity(): self
    {
        $this->update(['last_activity_at' => now()]);

        return $this;
    }

    public function close(): self
    {
        $this->update([
            'status' => 'closed',
            'ended_at' => now(),
        ]);

        return $this;
    }

    public function isExpired(int $timeoutSeconds = 300): bool
    {
        if ($this->status === 'expired' || $this->status === 'closed') {
            return true;
        }

        $lastActivity = $this->last_activity_at ?? $this->started_at;

        return $lastActivity && $lastActivity->diffInSeconds(now()) > $timeoutSeconds;
    }
}
