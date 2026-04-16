<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmbeddedDashboard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmbeddedDashboard>
 */
class EmbeddedDashboardFactory extends Factory
{
    protected $model = EmbeddedDashboard::class;

    public function definition(): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'name' => $this->faker->words(2, true),
            'provider' => 'metabase',
            'embed_url' => 'https://example.com/embed/'.$this->faker->slug(),
            'is_active' => true,
            'display_order' => 1,
            'created_by' => $user->id,
        ];
    }
}
