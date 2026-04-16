<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalTicketControllerTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    private ClientPortalUser $portalUser;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->portalUser = ClientPortalUser::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Cliente Portal',
            'email' => 'cliente@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->portalUser, ['portal:access']);
    }

    private function createTicket(?int $tenantId = null, ?int $customerId = null, string $subject = 'Problema X'): int
    {
        return DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'customer_id' => $customerId ?? $this->customer->id,
            'created_by' => $this->portalUser->id,
            'ticket_number' => 'TKT-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'subject' => $subject,
            'description' => 'Descrição do problema',
            'priority' => 'normal',
            'status' => 'open',
            'source' => 'portal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_returns_only_current_tenant_tickets(): void
    {
        $this->createTicket();

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->createTicket($otherTenant->id, $otherCustomer->id, 'LEAK ticket');

        $response = $this->getJson('/api/v1/portal/tickets');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK ticket', $json);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createTicket();
        DB::table('portal_tickets')->where('tenant_id', $this->tenant->id)->update(['status' => 'closed']);
        $this->createTicket();

        $response = $this->getJson('/api/v1/portal/tickets?status=closed');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertEquals('closed', $row->status ?? $row['status']);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_ticket_with_auto_number(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Balança desregulada',
            'description' => 'A balança está apresentando variação de 50g',
            'priority' => 'high',
            'category' => 'equipment',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('portal_tickets', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subject' => 'Balança desregulada',
            'priority' => 'high',
            'status' => 'open',
        ]);
    }

    public function test_show_returns_ticket(): void
    {
        $id = $this->createTicket();

        $response = $this->getJson("/api/v1/portal/tickets/{$id}");

        $response->assertOk();
    }

    public function test_show_returns_404_for_cross_tenant_ticket(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignId = $this->createTicket($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson("/api/v1/portal/tickets/{$foreignId}");

        $response->assertStatus(404);
    }
}
