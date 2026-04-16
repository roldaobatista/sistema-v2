<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => fn (array $attrs) => Customer::factory()->create(['tenant_id' => $attrs['tenant_id']])->id,
            'created_by' => User::factory(),
            'invoice_number' => 'NF-'.str_pad(fake()->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'total' => fake()->randomFloat(2, 100, 5000),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_at' => now(),
            'nf_number' => (string) fake()->numberBetween(1000, 9999),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }
}
