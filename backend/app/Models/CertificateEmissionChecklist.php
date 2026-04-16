<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CertificateEmissionChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $equipment_identified
 * @property bool|null $scope_defined
 * @property bool|null $critical_analysis_done
 * @property bool|null $procedure_defined
 * @property bool|null $standards_traceable
 * @property bool|null $raw_data_recorded
 * @property bool|null $uncertainty_calculated
 * @property bool|null $adjustment_documented
 * @property bool|null $no_undue_interval
 * @property bool|null $conformity_declaration_valid
 * @property bool|null $accreditation_mark_correct
 * @property bool|null $approved
 * @property Carbon|null $verified_at
 */
class CertificateEmissionChecklist extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CertificateEmissionChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'equipment_calibration_id', 'verified_by',
        'equipment_identified', 'scope_defined', 'critical_analysis_done',
        'procedure_defined', 'standards_traceable', 'raw_data_recorded',
        'uncertainty_calculated', 'adjustment_documented', 'no_undue_interval',
        'conformity_declaration_valid', 'accreditation_mark_correct',
        'observations', 'approved', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'equipment_identified' => 'boolean',
            'scope_defined' => 'boolean',
            'critical_analysis_done' => 'boolean',
            'procedure_defined' => 'boolean',
            'standards_traceable' => 'boolean',
            'raw_data_recorded' => 'boolean',
            'uncertainty_calculated' => 'boolean',
            'adjustment_documented' => 'boolean',
            'no_undue_interval' => 'boolean',
            'conformity_declaration_valid' => 'boolean',
            'accreditation_mark_correct' => 'boolean',
            'approved' => 'boolean',
            'verified_at' => 'datetime',
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
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isComplete(): bool
    {
        return $this->equipment_identified
            && $this->scope_defined
            && $this->critical_analysis_done
            && $this->procedure_defined
            && $this->standards_traceable
            && $this->raw_data_recorded
            && $this->uncertainty_calculated
            && $this->adjustment_documented
            && $this->no_undue_interval
            && $this->conformity_declaration_valid
            && $this->accreditation_mark_correct;
    }
}
