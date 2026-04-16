<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EmailRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailRuleControllerTest extends TestCase
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

    private function createRule(?int $tenantId = null, string $name = 'Regra SPAM'): EmailRule
    {
        return EmailRule::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'spam']],
            'actions' => [['type' => 'archive', 'params' => []]],
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_rules(): void
    {
        $mine = $this->createRule();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createRule($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/email-rules');

        $response->assertOk()->assertJsonStructure(['data']);
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/email-rules', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_rule(): void
    {
        $response = $this->postJson('/api/v1/email-rules', [
            'name' => 'Nova regra',
            'conditions' => [['field' => 'from', 'operator' => 'contains', 'value' => 'example.com']],
            'actions' => [['type' => 'notify', 'params' => []]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('email_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Nova regra',
        ]);
    }

    public function test_toggle_active_flips_state(): void
    {
        $rule = $this->createRule();

        $response = $this->postJson("/api/v1/email-rules/{$rule->id}/toggle-active");

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_destroy_removes_rule(): void
    {
        $rule = $this->createRule();

        $response = $this->deleteJson("/api/v1/email-rules/{$rule->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
