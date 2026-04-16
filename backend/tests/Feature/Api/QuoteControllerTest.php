<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class QuoteControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    public function test_index_returns_quotes(): void
    {
        Quote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/quotes');
        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_store_creates_quote(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'Orçamento calibração',
            'validity_days' => 30,
            'general_conditions' => 'Condições de teste via API',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('quotes', [
            'general_conditions' => 'Condições de teste via API',
        ]);
    }

    public function test_show_returns_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/quotes/{$quote->id}");
        $response->assertOk();
    }

    public function test_update_modifies_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/quotes/{$quote->id}", [
            'title' => 'Orçamento atualizado',
            'general_conditions' => 'Nova condição de teste atualizada',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'general_conditions' => 'Nova condição de teste atualizada',
        ]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/quotes/{$quote->id}");
        $response->assertNoContent();
    }

    public function test_store_requires_customer(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/quotes', []);
        $response->assertUnprocessable();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/quotes');
        $response->assertUnauthorized();
    }

    public function test_tenant_isolation(): void
    {
        Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        $other = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'current_tenant_id' => $other->id]);
        $otherUser->tenants()->attach($other->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $this->tenant->id);
        $response = $this->actingAs($otherUser)->getJson('/api/v1/quotes');
        $this->assertEmpty($response->json('data'));
    }
}
