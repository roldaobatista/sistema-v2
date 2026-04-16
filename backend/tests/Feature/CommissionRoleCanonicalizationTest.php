<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionRoleCanonicalizationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_rule_normalizes_english_role_alias_to_canonical_value(): void
    {
        $response = $this->postJson('/api/v1/commission-rules', [
            'name' => 'Regra alias tecnico',
            'calculation_type' => 'percent_gross',
            'value' => 5,
            'applies_to_role' => 'technician',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.applies_to_role', 'tecnico');
    }

    public function test_rules_filter_accepts_english_alias_and_matches_canonical_records(): void
    {
        $created = $this->postJson('/api/v1/commission-rules', [
            'name' => 'Regra filtro vendedor',
            'calculation_type' => 'percent_gross',
            'value' => 7,
            'applies_to_role' => 'vendedor',
        ])->assertCreated()->json('data.id');

        $response = $this->getJson('/api/v1/commission-rules?applies_to_role=seller');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created)
            ->assertJsonPath('data.0.applies_to_role', 'vendedor');
    }

    public function test_store_campaign_normalizes_english_role_alias_to_canonical_value(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha alias motorista',
            'multiplier' => 1.5,
            'applies_to_role' => 'driver',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.applies_to_role', 'motorista');
    }
}
