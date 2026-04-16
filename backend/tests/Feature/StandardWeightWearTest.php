<?php

namespace Tests\Feature;

use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StandardWeightWearTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_can_predict_standard_weight_wear(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        $user->givePermissionTo(Permission::findOrCreate('equipments.standard_weight.view', 'web'));

        $weight = StandardWeight::factory()->create([
            'tenant_id' => $tenant->id,
            'nominal_value' => 20.0000,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/standard-weights/{$weight->id}/predict-wear");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'weight_id',
                'name',
                'wear_rate_percentage',
                'expected_failure_date',
            ]]);
    }
}
