<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\SetsCreatedBy;
use Database\Factories\LgpdDataRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $deadline
 * @property Carbon|null $responded_at
 */
class LgpdDataRequest extends Model
{
    use Auditable, BelongsToTenant;

    /** @use HasFactory<LgpdDataRequestFactory> */
    use HasFactory;

    use SetsCreatedBy;

    public const TYPE_ACCESS = 'access';

    public const TYPE_DELETION = 'deletion';

    public const TYPE_PORTABILITY = 'portability';

    public const TYPE_RECTIFICATION = 'rectification';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DENIED = 'denied';

    protected $fillable = [
        'tenant_id', 'protocol', 'holder_name', 'holder_email',
        'holder_document', 'request_type', 'status', 'description',
        'response_notes', 'response_file_path', 'deadline',
        'responded_at', 'responded_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public static function generateProtocol(int $tenantId): string
    {
        $year = now()->format('Y');
        $count = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('LGPD-%s-%04d', $year, $count);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->deadline->isPast();
    }
}
