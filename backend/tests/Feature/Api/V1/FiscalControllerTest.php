<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiscalControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_notes(): void
    {
        // 2 notas do tenant atual
        $currentNotes = FiscalNote::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // 3 notas de outro tenant (nao podem vazar)
        $otherTenant = Tenant::factory()->create();
        $foreignNotes = FiscalNote::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson('/api/v1/fiscal/notas');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'tenant_id'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $this->assertEqualsCanonicalizing(
            $currentNotes->pluck('id')->map(fn ($id) => (int) $id)->all(),
            array_map('intval', array_column($data, 'id'))
        );
        $this->assertSame(
            array_fill(0, 2, $this->tenant->id),
            array_map('intval', array_column($data, 'tenant_id')),
            'Nota fiscal de outro tenant vazou'
        );
        $this->assertEmpty(array_intersect(
            $foreignNotes->pluck('id')->map(fn ($id) => (int) $id)->all(),
            array_map('intval', array_column($data, 'id'))
        ));
    }

    public function test_stats_endpoint_responds_successfully(): void
    {
        $response = $this->getJson('/api/v1/fiscal/stats');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_contingency_status_endpoint_responds(): void
    {
        $response = $this->getJson('/api/v1/fiscal/contingency/status');

        $response->assertOk();
    }

    public function test_show_returns_404_for_cross_tenant_note(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignNote = FiscalNote::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/fiscal/notas/{$foreignNote->id}");

        $response->assertNotFound();
    }

    public function test_emit_nfe_validates_request(): void
    {
        $response = $this->postJson('/api/v1/fiscal/nfe', []);

        // Validation deve rejeitar request vazio
        $response->assertStatus(422);
    }
}
