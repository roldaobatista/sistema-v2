<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TravelExpenseReport;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelExpenseReport>
 */
class TravelExpenseReportFactory extends Factory
{
    protected $model = TravelExpenseReport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'travel_request_id' => TravelRequest::factory(),
            'created_by' => User::factory(),
            'total_expenses' => 0,
            'total_advances' => 0,
            'balance' => 0,
            'status' => 'draft',
        ];
    }
}
