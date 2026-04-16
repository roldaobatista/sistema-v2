<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeClockEntryFactory extends Factory
{
    protected $model = TimeClockEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'type' => 'regular',
        ];
    }
}
