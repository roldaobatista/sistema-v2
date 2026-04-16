<?php

namespace Database\Factories;

use App\Models\CertificateEmissionChecklist;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateEmissionChecklist>
 */
class CertificateEmissionChecklistFactory extends Factory
{
    protected $model = CertificateEmissionChecklist::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'equipment_calibration_id' => EquipmentCalibration::factory(),
            'verified_by' => User::factory(),
            'equipment_identified' => true,
            'scope_defined' => true,
            'critical_analysis_done' => true,
            'procedure_defined' => true,
            'standards_traceable' => true,
            'raw_data_recorded' => true,
            'uncertainty_calculated' => true,
            'adjustment_documented' => true,
            'no_undue_interval' => true,
            'conformity_declaration_valid' => true,
            'accreditation_mark_correct' => true,
            'approved' => true,
            'verified_at' => now(),
        ];
    }

    public function incomplete(): static
    {
        return $this->state(fn () => [
            'uncertainty_calculated' => false,
            'approved' => false,
        ]);
    }
}
