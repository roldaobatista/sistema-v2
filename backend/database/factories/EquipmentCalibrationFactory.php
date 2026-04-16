<?php

namespace Database\Factories;

use App\Models\CalibrationReading;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\ExcentricityTest;
use App\Models\RepeatabilityTest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentCalibrationFactory extends Factory
{
    protected $model = EquipmentCalibration::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'equipment_id' => Equipment::factory(),
            'calibration_date' => now(),
            'next_due_date' => now()->addYear(),
            'calibration_type' => fake()->randomElement(['internal', 'external']),
            'result' => 'approved',
            'laboratory' => fake()->company(),
            'certificate_number' => fake()->numerify('CERT-####'),
            'standard_used' => fake()->sentence(3),
            'error_found' => fake()->randomFloat(4, 0, 0.05),
            'uncertainty' => fake()->randomFloat(4, 0.001, 0.01),
            'temperature' => fake()->randomFloat(2, 18, 25),
            'humidity' => fake()->randomFloat(2, 30, 70),
            'pressure' => fake()->randomFloat(2, 1000, 1030),
            'cost' => fake()->randomFloat(2, 100, 5000),
            'mass_unit' => 'g',
            'notes' => fake()->sentence(),
        ];
    }

    /**
     * Calibração reprovada.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'result' => 'rejected',
            'error_found' => fake()->randomFloat(4, 1, 10),
        ]);
    }

    /**
     * Condições ambientais controladas (laboratório ISO 17025).
     */
    public function withEnvironment(): static
    {
        return $this->state(fn () => [
            'temperature' => 20.0,
            'humidity' => 50.0,
            'pressure' => 1013.25,
            'gravity_acceleration' => 9.7864,
        ]);
    }

    /**
     * Campos do wizard de calibração preenchidos.
     */
    public function withWizardFields(): static
    {
        return $this->state(fn () => [
            'calibration_method' => 'comparison',
            'calibration_location' => fake()->company(),
            'calibration_location_type' => 'laboratory',
            'verification_type' => 'initial',
            'verification_division_e' => 0.001,
            'mass_unit' => 'g',
            'conformity_declaration' => 'Aprovado conforme Portaria INMETRO 157/2022',
            'max_permissible_error' => 0.05,
            'max_error_found' => 0.02,
            'decision_rule' => 'simple',
        ]);
    }

    /**
     * Calibração completa com ambiente, wizard e sub-registros (readings, excentricidade, repetibilidade).
     */
    public function complete(): static
    {
        return $this->withEnvironment()
            ->withWizardFields()
            ->has(CalibrationReading::factory()->count(5), 'readings')
            ->has(ExcentricityTest::factory()->count(5), 'excentricityTests')
            ->has(RepeatabilityTest::factory()->count(1), 'repeatabilityTests');
    }

    /**
     * Calibração com técnico e aprovador definidos.
     */
    public function withPersonnel(): static
    {
        return $this->state(fn () => [
            'performed_by' => User::factory(),
            'approved_by' => User::factory(),
        ]);
    }
}
