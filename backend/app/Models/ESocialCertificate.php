<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property bool|null $is_active
 */
class ESocialCertificate extends Model
{
    use BelongsToTenant;

    protected $table = 'esocial_certificates';

    protected $fillable = [
        'tenant_id',
        'certificate_path',
        'certificate_password_encrypted',
        'serial_number',
        'issuer',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];

    }

    protected $hidden = [
        'certificate_password_encrypted',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Accessors ──

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->valid_until) {
            return false;
        }

        return $this->valid_until->isPast();
    }

    // ── Appended attributes ──

    protected $appends = ['is_expired'];
}
