<?php

namespace Database\Factories;

use App\Models\InmetroSeal;
use App\Models\PseiSubmission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PseiSubmissionFactory extends Factory
{
    protected $model = PseiSubmission::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'seal_id' => fn (array $attributes) => InmetroSeal::factory()->seloReparo()->state(['tenant_id' => $attributes['tenant_id']]),
            'submission_type' => PseiSubmission::TYPE_AUTOMATIC,
            'status' => PseiSubmission::STATUS_QUEUED,
            'attempt_number' => 1,
            'max_attempts' => 3,
        ];
    }

    public function successful(): static
    {
        return $this->state([
            'status' => PseiSubmission::STATUS_SUCCESS,
            'protocol_number' => fake()->numerify('PSEI-########'),
            'submitted_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => PseiSubmission::STATUS_FAILED,
            'error_message' => 'Connection timeout',
            'submitted_at' => now(),
            'next_retry_at' => now()->addMinutes(5),
        ]);
    }

    public function captchaBlocked(): static
    {
        return $this->state([
            'status' => PseiSubmission::STATUS_CAPTCHA_BLOCKED,
            'error_message' => 'CAPTCHA detected on PSEI portal',
            'submitted_at' => now(),
        ]);
    }

    public function manual(): static
    {
        return $this->state(['submission_type' => PseiSubmission::TYPE_MANUAL]);
    }

    public function retry(): static
    {
        return $this->state([
            'submission_type' => PseiSubmission::TYPE_RETRY,
            'attempt_number' => 2,
        ]);
    }
}
