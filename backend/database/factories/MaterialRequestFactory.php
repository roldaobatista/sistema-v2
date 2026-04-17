<?php

namespace Database\Factories;

use App\Models\MaterialRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialRequestFactory extends Factory
{
    protected $model = MaterialRequest::class;

    public function definition()
    {
        return [
            'tenant_id' => Tenant::factory(),
            'reference' => 'MR-'.$this->faker->unique()->randomNumber(5),
            'requester_id' => User::factory(),
            'status' => MaterialRequest::STATUS_PENDING,
            'priority' => MaterialRequest::PRIORITY_NORMAL,
        ];
    }
}
