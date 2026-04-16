<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $load_applied
 * @property numeric-string|null $indication
 * @property numeric-string|null $error
 * @property numeric-string|null $max_permissible_error
 * @property bool|null $conforms
 */
class ExcentricityTest extends Model
{
    use BelongsToTenant, HasFactory;

    public const POSITIONS = [
        'center' => 'Centro',
        'front_left' => 'Frente Esquerda',
        'front_right' => 'Frente Direita',
        'rear_left' => 'Traseira Esquerda',
        'rear_right' => 'Traseira Direita',
        'left_center' => 'Centro Esquerda',
        'right_center' => 'Centro Direita',
    ];

    protected $fillable = [
        'tenant_id', 'equipment_calibration_id', 'position',
        'load_applied', 'indication', 'error', 'max_permissible_error',
        'conforms', 'position_order',
    ];

    protected function casts(): array
    {
        return [
            'load_applied' => 'decimal:4',
            'indication' => 'decimal:4',
            'error' => 'decimal:4',
            'max_permissible_error' => 'decimal:4',
            'conforms' => 'boolean',
        ];
    }

    public function calibration(): BelongsTo
    {
        return $this->belongsTo(EquipmentCalibration::class, 'equipment_calibration_id');
    }

    public function calculateError(): void
    {
        $this->error = Decimal::string((float) $this->indication - (float) $this->load_applied, 4);
        if ($this->max_permissible_error !== null) {
            $this->conforms = abs((float) $this->error) <= abs((float) $this->max_permissible_error);
        }
    }
}
