<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Calibration\EmaCalculator;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $reference_value
 * @property numeric-string|null $indication_increasing
 * @property numeric-string|null $indication_decreasing
 * @property numeric-string|null $error
 * @property numeric-string|null $expanded_uncertainty
 * @property numeric-string|null $k_factor
 * @property numeric-string|null $correction
 * @property numeric-string|null $max_permissible_error
 * @property bool|null $ema_conforms
 * @property numeric-string|null $temperature
 * @property numeric-string|null $humidity
 * @property-read EquipmentCalibration|null $calibration
 */
class CalibrationReading extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'equipment_calibration_id', 'reference_value',
        'indication_increasing', 'indication_decreasing', 'error',
        'expanded_uncertainty', 'k_factor', 'correction',
        'max_permissible_error', 'ema_conforms',
        'reading_order', 'repetition', 'unit',
        'temperature', 'humidity',
    ];

    protected function casts(): array
    {
        return [
            'reference_value' => 'decimal:4',
            'indication_increasing' => 'decimal:4',
            'indication_decreasing' => 'decimal:4',
            'error' => 'decimal:4',
            'expanded_uncertainty' => 'decimal:4',
            'k_factor' => 'decimal:2',
            'correction' => 'decimal:4',
            'max_permissible_error' => 'decimal:6',
            'ema_conforms' => 'boolean',
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
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
     * Calcula o erro a partir da indicação e valor de referência.
     */
    public function calculateError(): void
    {
        if ($this->indication_increasing !== null) {
            $this->error = Decimal::string((float) $this->indication_increasing - (float) $this->reference_value, 4);
        }
        $this->correction = $this->error !== null ? Decimal::string(-(float) $this->error, 4) : null;
    }

    /**
     * Calcula e persiste o EMA (Erro Máximo Admissível) conforme OIML R76.
     * Requer que a calibração tenha precision_class e e_value no equipamento.
     */
    public function calculateEma(): void
    {
        $calibration = $this->calibration;
        if (! $calibration) {
            return;
        }

        $precisionClass = $calibration->precision_class;
        $eValue = (float) ($calibration->verification_division_e ?? 0);

        if (! $precisionClass || $eValue <= 0 || $this->reference_value === null) {
            return;
        }

        $loadValue = abs((float) $this->reference_value);
        if ($loadValue <= 0) {
            return;
        }

        $this->max_permissible_error = Decimal::string(EmaCalculator::calculate(
            $precisionClass,
            $eValue,
            $loadValue
        ), 6);

        if ($this->error !== null) {
            $this->ema_conforms = EmaCalculator::isConforming(
                (float) $this->error,
                (float) $this->max_permissible_error
            );
        }
    }
}
