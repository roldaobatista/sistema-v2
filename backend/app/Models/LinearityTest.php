<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Calibration\EmaCalculator;
use App\Support\Decimal;
use Database\Factories\LinearityTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $reference_value
 * @property numeric-string|null $indication_increasing
 * @property numeric-string|null $indication_decreasing
 * @property numeric-string|null $error_increasing
 * @property numeric-string|null $error_decreasing
 * @property numeric-string|null $hysteresis
 * @property numeric-string|null $max_permissible_error
 * @property bool|null $conforms
 */
class LinearityTest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LinearityTestFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'equipment_calibration_id', 'point_order',
        'reference_value', 'unit',
        'indication_increasing', 'indication_decreasing',
        'error_increasing', 'error_decreasing',
        'hysteresis', 'max_permissible_error', 'conforms',
    ];

    protected function casts(): array
    {
        return [
            'reference_value' => 'decimal:4',
            'indication_increasing' => 'decimal:4',
            'indication_decreasing' => 'decimal:4',
            'error_increasing' => 'decimal:4',
            'error_decreasing' => 'decimal:4',
            'hysteresis' => 'decimal:4',
            'max_permissible_error' => 'decimal:4',
            'conforms' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<EquipmentCalibration, $this>
     */
    public function calibration(): BelongsTo
    {
        return $this->belongsTo(EquipmentCalibration::class, 'equipment_calibration_id');
    }

    /**
     * Calculate errors, hysteresis, and conformity based on indications and EMA.
     */
    public function calculateErrors(?string $precisionClass = null, ?float $eValue = null, string $verificationType = 'initial'): void
    {
        if ($this->indication_increasing !== null) {
            $this->error_increasing = Decimal::string((float) $this->indication_increasing - (float) $this->reference_value, 4);
        }

        if ($this->indication_decreasing !== null) {
            $this->error_decreasing = Decimal::string((float) $this->indication_decreasing - (float) $this->reference_value, 4);
        }

        if ($this->indication_increasing !== null && $this->indication_decreasing !== null) {
            $this->hysteresis = Decimal::string(abs((float) $this->indication_increasing - (float) $this->indication_decreasing), 4);
        }

        if ($precisionClass && $eValue && $eValue > 0 && $this->reference_value > 0) {
            $this->max_permissible_error = Decimal::string(EmaCalculator::calculate(
                $precisionClass,
                $eValue,
                abs((float) $this->reference_value),
                $verificationType
            ), 4);

            $ema = (float) $this->max_permissible_error;
            $errorIncConforms = $this->error_increasing === null || EmaCalculator::isConforming((float) $this->error_increasing, $ema);
            $errorDecConforms = $this->error_decreasing === null || EmaCalculator::isConforming((float) $this->error_decreasing, $ema);
            $hysteresisConforms = $this->hysteresis === null || EmaCalculator::isConforming((float) $this->hysteresis, $ema);

            $this->conforms = $errorIncConforms && $errorDecConforms && $hysteresisConforms;
        }
    }
}
