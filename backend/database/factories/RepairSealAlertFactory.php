<?php

namespace Database\Factories;

use App\Models\InmetroSeal;
use App\Models\RepairSealAlert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepairSealAlertFactory extends Factory
{
    protected $model = RepairSealAlert::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'seal_id' => fn (array $attributes) => InmetroSeal::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'technician_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'alert_type' => RepairSealAlert::TYPE_WARNING_3D,
            'severity' => RepairSealAlert::SEVERITY_WARNING,
            'message' => fake()->sentence(),
        ];
    }

    public function warning3d(): static
    {
        return $this->state([
            'alert_type' => RepairSealAlert::TYPE_WARNING_3D,
            'severity' => RepairSealAlert::SEVERITY_WARNING,
            'message' => 'Selo usado há 3 dias sem registro no PSEI.',
        ]);
    }

    public function critical4d(): static
    {
        return $this->state([
            'alert_type' => RepairSealAlert::TYPE_CRITICAL_4D,
            'severity' => RepairSealAlert::SEVERITY_CRITICAL,
            'message' => 'URGENTE: Selo usado há 4 dias sem registro no PSEI. Escalado ao gerente.',
        ]);
    }

    public function overdue5d(): static
    {
        return $this->state([
            'alert_type' => RepairSealAlert::TYPE_OVERDUE_5D,
            'severity' => RepairSealAlert::SEVERITY_CRITICAL,
            'message' => 'VENCIDO: Selo excedeu prazo de 5 dias. Técnico bloqueado.',
        ]);
    }

    public function lowStock(): static
    {
        return $this->state([
            'alert_type' => RepairSealAlert::TYPE_LOW_STOCK,
            'severity' => RepairSealAlert::SEVERITY_INFO,
            'message' => 'Estoque baixo de selos para o técnico.',
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now(),
            'acknowledged_by' => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now()->subHour(),
            'acknowledged_by' => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'resolved_at' => now(),
            'resolved_by' => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
        ]);
    }
}
