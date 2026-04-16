<?php

namespace Database\Factories;

use App\Models\LgpdDataRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LgpdDataRequest>
 */
class LgpdDataRequestFactory extends Factory
{
    protected $model = LgpdDataRequest::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'protocol' => 'LGPD-'.now()->format('Y').'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'holder_name' => $this->faker->name(),
            'holder_email' => $this->faker->safeEmail(),
            'holder_document' => $this->faker->numerify('###########'),
            'request_type' => $this->faker->randomElement(['access', 'deletion', 'portability', 'rectification']),
            'status' => LgpdDataRequest::STATUS_PENDING,
            'description' => $this->faker->sentence(),
            'deadline' => now()->addWeekdays(15),
            'created_by' => User::factory(),
        ];
    }
}
