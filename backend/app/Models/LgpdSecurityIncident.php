<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $holders_notified
 * @property Carbon|null $holders_notified_at
 * @property Carbon|null $detected_at
 * @property Carbon|null $anpd_reported_at
 * @property int|null $affected_holders_count
 */
class LgpdSecurityIncident extends Model
{
    use Auditable, BelongsToTenant;

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_OPEN = 'open';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_CONTAINED = 'contained';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'tenant_id', 'protocol', 'severity', 'description',
        'affected_data', 'affected_holders_count', 'measures_taken',
        'anpd_notification', 'holders_notified', 'holders_notified_at',
        'detected_at', 'anpd_reported_at', 'status', 'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'holders_notified' => 'boolean',
            'holders_notified_at' => 'datetime',
            'detected_at' => 'datetime',
            'anpd_reported_at' => 'datetime',
            'affected_holders_count' => 'integer',
        ];
    }

    public static function generateProtocol(int $tenantId): string
    {
        $year = now()->format('Y');
        $count = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('INC-%s-%04d', $year, $count);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
