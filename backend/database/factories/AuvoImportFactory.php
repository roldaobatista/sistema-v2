<?php

namespace Database\Factories;

use App\Models\AuvoImport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuvoImportFactory extends Factory
{
    protected $model = AuvoImport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'file_name' => 'auvo_export_'.fake()->date('Ymd').'.csv',
            'status' => AuvoImport::STATUS_PENDING,
            'total_rows' => fake()->numberBetween(10, 500),
            'processed_rows' => 0,
        ];
    }
}
