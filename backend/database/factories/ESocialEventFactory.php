<?php

namespace Database\Factories;

use App\Models\ESocialEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ESocialEventFactory extends Factory
{
    protected $model = ESocialEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'event_type' => $this->faker->randomElement(array_keys(ESocialEvent::EVENT_TYPES)),
            'status' => 'pending',
            'xml_content' => '<?xml version="1.0" encoding="UTF-8"?><eSocial><stub/></eSocial>',
            'environment' => 'restricted',
            'version' => 'S-1.2',
            'retry_count' => 0,
            'max_retries' => 3,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'sent_at' => now(),
            'batch_id' => 'BATCH-'.now()->format('YmdHis').'-'.uniqid(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'sent_at' => now()->subHour(),
            'response_at' => now(),
            'receipt_number' => 'REC-'.$this->faker->numerify('########'),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'sent_at' => now()->subHour(),
            'response_at' => now(),
            'error_message' => 'Erro de validação no XML',
        ]);
    }

    public function exhaustedRetries(): static
    {
        return $this->rejected()->state(fn () => [
            'retry_count' => 3,
            'max_retries' => 3,
            'last_retry_at' => now(),
        ]);
    }

    public function s1000(): static
    {
        return $this->state(fn () => ['event_type' => 'S-1000']);
    }

    public function s2200(): static
    {
        return $this->state(fn () => ['event_type' => 'S-2200']);
    }
}
