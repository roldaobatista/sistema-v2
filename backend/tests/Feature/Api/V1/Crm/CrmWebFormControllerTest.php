<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmWebForm;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmWebFormControllerTest extends TestCase
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

    private function createForm(?int $tenantId = null, string $name = 'Formulário Site'): CrmWebForm
    {
        return CrmWebForm::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'slug' => 'form-'.uniqid(),
            'description' => 'desc',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
            ],
            'is_active' => true,
        ]);
    }

    public function test_web_forms_returns_only_current_tenant(): void
    {
        $mine = $this->createForm();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createForm($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/crm-features/web-forms');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_web_form_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-features/web-forms', []);

        $response->assertStatus(422);
    }

    public function test_store_web_form_creates_with_slug_and_tenant(): void
    {
        $response = $this->postJson('/api/v1/crm-features/web-forms', [
            'name' => 'Landing Page Leads',
            'description' => 'Formulário da landing page',
            'fields' => [
                ['name' => 'nome', 'type' => 'text', 'label' => 'Nome', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_web_forms', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Landing Page Leads',
        ]);

        $form = CrmWebForm::where('name', 'Landing Page Leads')->first();
        $this->assertNotNull($form->slug);
        $this->assertStringContainsString('landing-page-leads', $form->slug);
    }

    public function test_update_web_form_rejects_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createForm($otherTenant->id, 'Foreign');

        $response = $this->putJson("/api/v1/crm-features/web-forms/{$foreign->id}", [
            'name' => 'Hackeado',
            'fields' => [['name' => 'x', 'type' => 'text', 'label' => 'X', 'required' => false]],
        ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('crm_web_forms', ['id' => $foreign->id, 'name' => 'Foreign']);
    }

    public function test_web_form_options_returns_current_tenant_data(): void
    {
        $response = $this->getJson('/api/v1/crm-features/web-forms/options');

        $response->assertOk()->assertJsonStructure(['data' => ['pipelines', 'sequences', 'users']]);
    }
}
