<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\MaintenanceReport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceReport>
 */
class MaintenanceReportFactory extends Factory
{
    protected $model = MaintenanceReport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_order_id' => WorkOrder::factory(),
            'equipment_id' => Equipment::factory(),
            'performed_by' => User::factory(),
            'defect_found' => $this->faker->sentence(),
            'probable_cause' => $this->faker->sentence(),
            'corrective_action' => $this->faker->sentence(),
            'condition_before' => 'defective',
            'condition_after' => 'functional',
            'requires_calibration_after' => true,
            'requires_ipem_verification' => false,
        ];
    }
}
