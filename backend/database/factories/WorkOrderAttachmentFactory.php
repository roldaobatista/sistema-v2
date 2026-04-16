<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderAttachmentFactory extends Factory
{
    protected $model = WorkOrderAttachment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_order_id' => WorkOrder::factory(),
            'uploaded_by' => User::factory(),
            'file_name' => fake()->word().'.pdf',
            'file_path' => 'attachments/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(1024, 5242880),
            'mime_type' => 'application/pdf',
        ];
    }
}
