<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $calibration_date
 * @property Carbon|null $next_due_date
 * @property Carbon|null $received_date
 * @property Carbon|null $issued_date
 * @property array<int|string, mixed>|null $errors_found
 * @property numeric-string|null $error_found
 * @property numeric-string|null $uncertainty
 * @property numeric-string|null $temperature
 * @property numeric-string|null $humidity
 * @property numeric-string|null $pressure
 * @property numeric-string|null $cost
 * @property int|null $work_order_id
 * @property string|null $calibration_type
 * @property string|null $laboratory
 * @property array<int|string, mixed>|null $eccentricity_data
 * @property array<int|string, mixed>|null $before_adjustment_data
 * @property array<int|string, mixed>|null $after_adjustment_data
 * @property numeric-string|null $verification_division_e
 * @property numeric-string|null $gravity_acceleration
 * @property array<int|string, mixed>|null $uncertainty_budget
 * @property numeric-string|null $max_permissible_error
 * @property numeric-string|null $max_error_found
 * @property string|null $precision_class
 * @property string|null $verification_type
 * @property Carbon|null $calibration_started_at
 * @property Carbon|null $calibration_completed_at
 * @property bool|null $adjustment_performed
 * @property numeric-string|null $coverage_factor_k
 * @property numeric-string|null $confidence_level
 * @property numeric-string|null $guard_band_value
 * @property numeric-string|null $producer_risk_alpha
 * @property numeric-string|null $consumer_risk_beta
 * @property numeric-string|null $decision_z_value
 * @property numeric-string|null $decision_false_accept_prob
 * @property numeric-string|null $decision_guard_band_applied
 * @property Carbon|null $decision_calculated_at
 * @property int|null $decision_calculated_by
 * @property-read Equipment|null $equipment
 * @property-read Collection<int, StandardWeight> $standardWeights
 * @property-read Collection<int, LinearityTest> $linearityTests
 */
class EquipmentCalibration extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'equipment_id', 'calibration_date', 'next_due_date',
        'calibration_type', 'result', 'laboratory', 'certificate_number',
        'certificate_file', 'certificate_pdf_path', 'standard_used',
        'error_found', 'uncertainty', 'errors_found', 'technician_notes',
        'temperature', 'humidity', 'pressure',
        'corrections_applied', 'performed_by', 'approved_by',
        'cost', 'work_order_id', 'notes', 'eccentricity_data',
        'certificate_template_id', 'conformity_declaration',
        'max_permissible_error', 'max_error_found', 'mass_unit', 'calibration_method',
        // Wizard / ISO 17025 fields
        'received_date', 'issued_date', 'calibration_location',
        'calibration_location_type', 'before_adjustment_data', 'after_adjustment_data',
        'verification_type', 'verification_division_e', 'prefilled_from_id',
        'gravity_acceleration', 'decision_rule', 'uncertainty_budget',
        'laboratory_address', 'scope_declaration', 'precision_class',
        // Normative compliance fields
        'calibration_started_at', 'calibration_completed_at',
        'condition_as_found', 'condition_as_left', 'adjustment_performed',
        'accreditation_scope_id',
        // ISO 17025 §7.8.6 decision rule parameters (ILAC G8:09/2019 + P14:09/2020)
        'coverage_factor_k', 'confidence_level',
        'guard_band_mode', 'guard_band_value',
        'producer_risk_alpha', 'consumer_risk_beta',
        // Decision result (computed)
        'decision_result', 'decision_z_value', 'decision_false_accept_prob',
        'decision_guard_band_applied', 'decision_calculated_at',
        'decision_calculated_by', 'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'calibration_date' => 'date',
            'next_due_date' => 'date',
            'received_date' => 'date',
            'issued_date' => 'date',
            'errors_found' => 'array',
            'error_found' => 'decimal:4',
            'uncertainty' => 'decimal:4',
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'pressure' => 'decimal:2',
            'cost' => 'decimal:2',
            'eccentricity_data' => 'array',
            'before_adjustment_data' => 'array',
            'after_adjustment_data' => 'array',
            'verification_division_e' => 'decimal:6',
            'gravity_acceleration' => 'decimal:6',
            'uncertainty_budget' => 'array',
            'max_permissible_error' => 'decimal:4',
            'max_error_found' => 'decimal:4',
            'calibration_started_at' => 'datetime',
            'calibration_completed_at' => 'datetime',
            'adjustment_performed' => 'boolean',
            // Decision rule parameters
            'coverage_factor_k' => 'decimal:2',
            'confidence_level' => 'decimal:2',
            'guard_band_value' => 'decimal:6',
            'producer_risk_alpha' => 'decimal:4',
            'consumer_risk_beta' => 'decimal:4',
            'decision_z_value' => 'decimal:4',
            'decision_false_accept_prob' => 'decimal:6',
            'decision_guard_band_applied' => 'decimal:6',
            'decision_calculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsToMany<StandardWeight, $this>
     */
    public function standardWeights(): BelongsToMany
    {
        return $this->belongsToMany(
            StandardWeight::class,
            'calibration_standard_weight',
            'equipment_calibration_id',
            'standard_weight_id'
        )->withTimestamps();
    }

    public function readings(): HasMany
    {
        return $this->hasMany(CalibrationReading::class);
    }

    public function excentricityTests(): HasMany
    {
        return $this->hasMany(ExcentricityTest::class);
    }

    public function repeatabilityTests(): HasMany
    {
        return $this->hasMany(RepeatabilityTest::class);
    }

    public function prefilledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prefilled_from_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /**
     * @return HasOne<CertificateEmissionChecklist, $this>
     */
    public function emissionChecklist(): HasOne
    {
        return $this->hasOne(CertificateEmissionChecklist::class);
    }

    /**
     * @return HasMany<MaintenanceReport, $this>
     */
    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class, 'work_order_id', 'work_order_id');
    }

    /**
     * @return HasMany<LinearityTest, $this>
     */
    public function linearityTests(): HasMany
    {
        return $this->hasMany(LinearityTest::class);
    }

    /**
     * @return BelongsTo<AccreditationScope, $this>
     */
    public function accreditationScope(): BelongsTo
    {
        return $this->belongsTo(AccreditationScope::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decisionCalculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_calculated_by');
    }

    /**
     * @return HasMany<CalibrationDecisionLog, $this>
     */
    public function decisionLogs(): HasMany
    {
        return $this->hasMany(CalibrationDecisionLog::class);
    }
}
