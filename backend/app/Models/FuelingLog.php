<?php

namespace App\Models;

use App\Enums\FuelingLogStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property int|null $work_order_id
 * @property Carbon|null $fueling_date
 * @property string|null $vehicle_plate
 * @property float|null $odometer_km
 * @property string|null $gas_station_name
 * @property float|null $gas_station_lat
 * @property float|null $gas_station_lng
 * @property string|null $fuel_type
 * @property float|null $liters
 * @property float|null $price_per_liter
 * @property float|null $total_amount
 * @property string|null $receipt_path
 * @property string|null $notes
 * @property FuelingLogStatus $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property bool $affects_technician_cash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 * @property-read WorkOrder|null $workOrder
 * @property-read User|null $approver
 */
class FuelingLog extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'work_order_id', 'fueling_date',
        'vehicle_plate', 'odometer_km', 'gas_station_name',
        'gas_station_lat', 'gas_station_lng', 'fuel_type',
        'liters', 'price_per_liter', 'total_amount',
        'receipt_path', 'notes', 'status', 'approved_by', 'approved_at',
        'rejection_reason', 'affects_technician_cash',
    ];

    protected function casts(): array
    {
        return [
            'fueling_date' => 'date',
            'odometer_km' => 'decimal:1',
            'gas_station_lat' => 'decimal:7',
            'gas_station_lng' => 'decimal:7',
            'liters' => 'decimal:2',
            'price_per_liter' => 'decimal:4',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'affects_technician_cash' => 'boolean',
            'status' => FuelingLogStatus::class,
        ];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'Pendente', 'color' => 'warning'],
        self::STATUS_APPROVED => ['label' => 'Aprovado', 'color' => 'success'],
        self::STATUS_REJECTED => ['label' => 'Rejeitado', 'color' => 'danger'],
    ];

    public const FUEL_TYPES = [
        'diesel' => 'Diesel',
        'diesel_s10' => 'Diesel S10',
        'gasolina' => 'Gasolina',
        'etanol' => 'Etanol',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            if ($log->liters && $log->price_per_liter && ! $log->total_amount) {
                $log->total_amount = (float) bcmul((string) $log->liters, (string) $log->price_per_liter, 2);
            }
        });

        static::updating(function (self $log) {
            if ($log->isDirty(['liters', 'price_per_liter'])) {
                $log->total_amount = (float) bcmul(
                    (string) ($log->liters ?? 0),
                    (string) ($log->price_per_liter ?? 0),
                    2
                );
            }
        });
    }
}
