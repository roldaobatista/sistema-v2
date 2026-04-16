<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $startDate = now()->addWeek()->startOfDay();
        $endDate = (clone $startDate)->addWeek();

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => 'vacation',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_count' => $startDate->diffInDays($endDate) + 1,
            'status' => 'pending',
            'reason' => fake()->sentence(),
        ];
    }
}
