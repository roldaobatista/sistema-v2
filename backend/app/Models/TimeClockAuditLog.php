<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
class TimeClockAuditLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'time_clock_entry_id', 'time_clock_adjustment_id',
        'action', 'performed_by', 'ip_address', 'user_agent', 'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];

    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class, 'time_clock_entry_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(TimeClockAdjustment::class, 'time_clock_adjustment_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public static function log(
        string $action,
        ?int $entryId = null,
        ?int $adjustmentId = null,
        ?array $metadata = null
    ): self {
        $user = auth()->user() ?? request()?->user();
        $request = request();

        return self::create([
            'tenant_id' => $user?->current_tenant_id ?? (app()->bound('current_tenant_id') ? app('current_tenant_id') : null),
            'time_clock_entry_id' => $entryId,
            'time_clock_adjustment_id' => $adjustmentId,
            'action' => $action,
            'performed_by' => $user?->id ?? 0,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
