<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string|null $code
 * @property float $nominal_value
 * @property string|null $unit
 * @property string|null $serial_number
 * @property string|null $manufacturer
 * @property string|null $precision_class
 * @property string|null $material
 * @property string|null $shape
 * @property string|null $certificate_number
 * @property Carbon|null $certificate_date
 * @property Carbon|null $certificate_expiry
 * @property string|null $certificate_file
 * @property string|null $laboratory
 * @property string $status
 * @property string|null $notes
 * @property float|null $wear_rate_percentage
 * @property Carbon|null $expected_failure_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, EquipmentCalibration> $calibrations
 */
class StandardWeight extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_IN_CALIBRATION = 'in_calibration';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const STATUS_DISCARDED = 'discarded';

    public const STATUSES = [
        self::STATUS_ACTIVE => ['label' => 'Ativo', 'color' => 'success'],
        self::STATUS_IN_CALIBRATION => ['label' => 'Em Calibração', 'color' => 'warning'],
        self::STATUS_OUT_OF_SERVICE => ['label' => 'Fora de Uso', 'color' => 'danger'],
        self::STATUS_DISCARDED => ['label' => 'Descartado', 'color' => 'muted'],
    ];

    public const PRECISION_CLASSES = [
        'E1' => 'Classe E1 (Referência)',
        'E2' => 'Classe E2 (Referência)',
        'F1' => 'Classe F1 (Fina)',
        'F2' => 'Classe F2 (Fina)',
        'M1' => 'Classe M1 (Média)',
        'M2' => 'Classe M2 (Média)',
        'M3' => 'Classe M3 (Ordinária)',
    ];

    public const UNITS = ['kg', 'g', 'mg'];

    public const SHAPES = [
        'cylindrical' => 'Cilíndrico',
        'rectangular' => 'Retangular',
        'disc' => 'Disco',
        'parallelepiped' => 'Paralelepípedo',
        'other' => 'Outro',
    ];

    protected $fillable = [
        'tenant_id', 'code', 'nominal_value', 'unit', 'serial_number',
        'manufacturer', 'precision_class', 'material', 'shape',
        'certificate_number', 'certificate_date', 'certificate_expiry',
        'certificate_file', 'laboratory', 'status', 'notes',
        'wear_rate_percentage', 'expected_failure_date',
        'laboratory_accreditation', 'traceability_chain',
    ];

    protected function casts(): array
    {
        return [
            'nominal_value' => 'decimal:4',
            'certificate_date' => 'date',
            'certificate_expiry' => 'date',
            'wear_rate_percentage' => 'decimal:2',
            'expected_failure_date' => 'date',
        ];
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiring($query, int $days = 30)
    {
        return $query->whereNotNull('certificate_expiry')
            ->where('certificate_expiry', '<=', now()->addDays($days))
            ->where('certificate_expiry', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('certificate_expiry')
            ->where('certificate_expiry', '<', now());
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getCertificateStatusAttribute(): string
    {
        if (! $this->certificate_expiry) {
            return 'sem_data';
        }
        if ($this->certificate_expiry->isPast()) {
            return 'vencido';
        }
        if ($this->certificate_expiry->diffInDays(now()) <= 30) {
            return 'vence_em_breve';
        }

        return 'em_dia';
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} — {$this->nominal_value} {$this->unit}";
    }

    // ─── Relationships ──────────────────────────────────────

    public function calibrations(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            EquipmentCalibration::class,
            'calibration_standard_weight',
            'standard_weight_id',
            'equipment_calibration_id'
        )
            ->withPivot('tenant_id')
            ->withTimestamps();

        return $this->tenant_id ? $relation->withPivotValue('tenant_id', $this->tenant_id) : $relation;
    }

    // ─── Code Generation ────────────────────────────────────

    public static function generateCode(int $tenantId): string
    {
        $sequence = NumberingSequence::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $tenantId, 'entity' => 'standard_weight'],
            ['prefix' => 'PP-', 'next_number' => 1, 'padding' => 4]
        );

        return $sequence->generateNext();
    }
}
