<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowUpControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createFollowUp(?int $tenantId = null): FollowUp
    {
        return FollowUp::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'followable_type' => Customer::class,
            'followable_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
            'scheduled_at' => now()->addDay(),
            'channel' => 'phone',
            'status' => 'pending',
            'notes' => 'Ligar amanhã',
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createFollowUp();

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = FollowUp::create([
            'tenant_id' => $otherTenant->id,
            'followable_type' => Customer::class,
            'followable_id' => $otherCustomer->id,
            'assigned_to' => $otherUser->id,
            'scheduled_at' => now()->addDay(),
            'channel' => 'phone',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/advanced/follow-ups');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/advanced/follow-ups', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_follow_up(): void
    {
        $response = $this->postJson('/api/v1/advanced/follow-ups', [
            'followable_type' => Customer::class,
            'followable_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'channel' => 'phone',
            'notes' => 'Confirmar reunião',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('follow_ups', [
            'tenant_id' => $this->tenant->id,
            'followable_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_complete_marks_as_completed(): void
    {
        $followUp = $this->createFollowUp();

        $response = $this->putJson("/api/v1/advanced/follow-ups/{$followUp->id}/complete", [
            'result' => 'converted',
            'notes' => 'Contato realizado com sucesso',
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_destroy_removes_follow_up(): void
    {
        $followUp = $this->createFollowUp();

        $response = $this->deleteJson("/api/v1/advanced/follow-ups/{$followUp->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
