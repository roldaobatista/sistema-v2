<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $request_payload
 * @property array<int|string, mixed>|null $response_payload
 */
class FiscalEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'fiscal_note_id',
        'tenant_id',
        'event_type',
        'protocol_number',
        'description',
        'request_payload',
        'response_payload',
        'status',
        'error_message',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];

    }

    // ─── Relationships ──────────────────────────────

    public function fiscalNote(): BelongsTo
    {
        return $this->belongsTo(FiscalNote::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ─────────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
