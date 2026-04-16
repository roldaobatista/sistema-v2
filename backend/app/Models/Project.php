<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $actual_start_date
 * @property Carbon|null $actual_end_date
 * @property numeric-string|null $budget
 * @property numeric-string|null $spent
 * @property numeric-string|null $progress_percent
 * @property numeric-string|null $hourly_rate
 * @property array<int|string, mixed>|null $tags
 */
class Project extends Model
{
    use Auditable, BelongsToTenant;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'crm_deal_id',
        'created_by',
        'code',
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'budget',
        'spent',
        'progress_percent',
        'billing_type',
        'hourly_rate',
        'tags',
        'manager_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'budget' => 'decimal:2',
            'spent' => 'decimal:2',
            'progress_percent' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'tags' => 'array',
        ];
    }

    public static function generateCode(): string
    {
        $cacheKey = 'seq_project_code';
        $lockKey = 'lock_'.$cacheKey;

        return Cache::lock($lockKey, 5)->block(5, function () use ($cacheKey): string {
            if (! Cache::has($cacheKey)) {
                $last = static::withoutGlobalScopes()->withTrashed()->max('code');
                $sequence = $last ? (int) preg_replace('/[^0-9]/', '', $last) : 0;
                Cache::forever($cacheKey, $sequence);
            }

            $next = Cache::increment($cacheKey);

            return 'PRJ-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CrmDeal, $this>
     */
    public function crmDeal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('order');
    }

    /**
     * @return HasMany<ProjectResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(ProjectResource::class);
    }

    /**
     * @return HasMany<ProjectTimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class);
    }
}
