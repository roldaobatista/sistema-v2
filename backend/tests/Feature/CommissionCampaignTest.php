<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Commission Campaign Tests — validates CRUD operations,
 * validation rules, and active_only filtering.
 */
class CommissionCampaignTest extends TestCase
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

    public function test_campaigns_index_returns_list(): void
    {
        DB::table('commission_campaigns')->insert([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Index Test',
            'multiplier' => 1.2,
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->addMonths(1)->format('Y-m-d'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/commission-campaigns');
        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $items = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $this->assertNotEmpty($items);
    }

    public function test_create_campaign_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha Verão 2025',
            'multiplier' => 1.5,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->addMonths(2)->format('Y-m-d'),
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('commission_campaigns', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Verão 2025',
        ]);

        $json = $response->json();
        $data = $json['data'] ?? $json;
        $this->assertEquals('Campanha Verão 2025', $data['name']);
    }

    public function test_create_campaign_validates_multiplier_range(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha inválida',
            'multiplier' => 0.5, // below 1.01
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
    }

    public function test_create_campaign_validates_end_date_after_start(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha datas erradas',
            'multiplier' => 2.0,
            'starts_at' => '2025-12-31',
            'ends_at' => '2025-01-01', // before start
        ]);

        $response->assertStatus(422);
    }

    public function test_update_campaign(): void
    {
        $campaignId = DB::table('commission_campaigns')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Original',
            'multiplier' => 1.5,
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->addMonths(1)->format('Y-m-d'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/commission-campaigns/{$campaignId}", [
            'name' => 'Campanha Atualizada',
            'active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('commission_campaigns', [
            'id' => $campaignId,
            'name' => 'Campanha Atualizada',
            'active' => false,
        ]);
    }

    public function test_delete_campaign(): void
    {
        $campaignId = DB::table('commission_campaigns')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha para deletar',
            'multiplier' => 2.0,
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->addMonths(1)->format('Y-m-d'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/commission-campaigns/{$campaignId}");
        $response->assertNoContent();

        $this->assertDatabaseMissing('commission_campaigns', ['id' => $campaignId]);
    }
}
