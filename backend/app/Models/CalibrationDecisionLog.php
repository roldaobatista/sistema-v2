<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de auditoria de cada avaliação de regra de decisão.
 * Referência: ISO/IEC 17025:2017 §8.5 (controle de registros de decisão).
 *
 * @property array<int|string, mixed>|null $inputs
 * @property array<int|string, mixed>|null $outputs
 */
class CalibrationDecisionLog extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'equipment_calibration_id',
        'user_id',
        'decision_rule',
        'inputs',
        'outputs',
        'engine_version',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'outputs' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
