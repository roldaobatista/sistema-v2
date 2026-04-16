<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AssetRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $acquisition_date
 * @property Carbon|null $last_depreciation_at
 * @property Carbon|null $disposed_at
 * @property numeric-string|null $acquisition_value
 * @property numeric-string|null $residual_value
 * @property numeric-string|null $depreciation_rate
 * @property numeric-string|null $accumulated_depreciation
 * @property numeric-string|null $current_book_value
 * @property numeric-string|null $disposal_value
 * @property int|null $useful_life_months
 * @property int|null $ciap_total_installments
 * @property int|null $ciap_installments_taken
 */
class AssetRecord extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AssetRecordFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DISPOSED = 'disposed';

    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'category',
        'acquisition_date',
        'acquisition_value',
        'residual_value',
        'useful_life_months',
        'depreciation_method',
        'depreciation_rate',
        'accumulated_depreciation',
        'current_book_value',
        'status',
        'location',
        'responsible_user_id',
        'nf_number',
        'nf_serie',
        'supplier_id',
        'fleet_vehicle_id',
        'crm_deal_id',
        'ciap_credit_type',
        'ciap_total_installments',
        'ciap_installments_taken',
        'last_depreciation_at',
        'disposed_at',
        'disposal_reason',
        'disposal_value',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'last_depreciation_at' => 'date',
            'disposed_at' => 'date',
            'acquisition_value' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'depreciation_rate' => 'decimal:4',
            'accumulated_depreciation' => 'decimal:2',
            'current_book_value' => 'decimal:2',
            'disposal_value' => 'decimal:2',
            'useful_life_months' => 'integer',
            'ciap_total_installments' => 'integer',
            'ciap_installments_taken' => 'integer',
        ];
    }

    public static function generateCode(int $tenantId): string
    {
        $sequence = NumberingSequence::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $tenantId, 'entity' => 'asset_record'],
            ['prefix' => 'AT-', 'next_number' => 1, 'padding' => 5]
        );

        return $sequence->generateNext();
    }

    public static function calculateDepreciationRate(string $method, int $usefulLifeMonths): float
    {
        $months = max($usefulLifeMonths, 1);
        $linearRate = round((12 / $months) * 100, 4);

        return match ($method) {
            'accelerated' => min(round($linearRate * 1.5, 4), 100.0),
            'units_produced' => $linearRate,
            default => $linearRate,
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<FleetVehicle, $this>
     */
    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<CrmDeal, $this>
     */
    public function crmDeal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    /**
     * @return HasMany<DepreciationLog, $this>
     */
    public function depreciationLogs(): HasMany
    {
        return $this->hasMany(DepreciationLog::class)->orderByDesc('reference_month');
    }

    /**
     * @return HasMany<AssetDisposal, $this>
     */
    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class)->orderByDesc('disposal_date');
    }

    /**
     * @return HasMany<AssetMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->orderByDesc('moved_at');
    }

    /**
     * @return HasMany<AssetInventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(AssetInventory::class)->orderByDesc('inventory_date');
    }
}
