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

/**
 * FiscalPublicController — endpoint publico de consulta DANFE por chave de acesso.
 *
 * Route: POST /api/v1/fiscal/consulta-publica (throttle:60,1, sem auth middleware).
 * Mas o FormRequest usa $this->user()->can('fiscal.view'), por isso testes
 * autenticam via Sanctum para evitar null pointer no authorize().
 */
class FiscalPublicControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_consulta_publica_validates_required_chave_acesso(): void
    {
        $response = $this->postJson('/api/v1/fiscal/consulta-publica', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chave_acesso']);
    }

    public function test_consulta_publica_rejects_chave_acesso_with_wrong_size(): void
    {
        // Regra size:44 — chave DANFE tem 44 digitos
        $response = $this->postJson('/api/v1/fiscal/consulta-publica', [
            'chave_acesso' => '12345', // muito curta
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chave_acesso']);
    }

    public function test_consulta_publica_returns_404_when_note_not_found(): void
    {
        $response = $this->postJson('/api/v1/fiscal/consulta-publica', [
            'chave_acesso' => str_repeat('9', 44), // chave inexistente, mas com 44 chars
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Nota fiscal não encontrada');
    }

    public function test_consulta_publica_returns_note_details_when_found(): void
    {
        $chave = str_repeat('1', 44);
        FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'access_key' => $chave,
            'status' => 'authorized', // FiscalNoteStatus enum values: pending|processing|authorized|cancelled|rejected
        ]);

        $response = $this->postJson('/api/v1/fiscal/consulta-publica', [
            'chave_acesso' => $chave,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'number',
                    'access_key',
                    'status',
                    'total_amount',
                ],
            ])
            ->assertJsonPath('data.access_key', $chave);
    }
}
