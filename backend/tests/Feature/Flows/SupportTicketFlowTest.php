<?php

namespace Tests\Feature\Flows;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\PortalTicket;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HelpdeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    private User $admin;

    private ClientPortalUser $portalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Ignora Middlewares de infra rotineira pra focar no fluxo
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
            EnsurePortalAccess::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->portalUser = ClientPortalUser::forceCreate([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal Test User',
            'email' => 'portal'.rand().'@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_ticket_creation_automatically_calculates_sla_due_at(): void
    {

        // Limpar existentes criadas possivelmente pelo TenantObserver etc
        SlaPolicy::where('tenant_id', $this->tenant->id)->delete();
        SlaPolicy::insert([
            'tenant_id' => $this->tenant->id,
            'name' => 'High SLA',
            'priority' => 'high',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->portalUser, ['portal:access']);
        app()->instance('current_tenant_id', $this->tenant->id);

        $response = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Falha Crítica no Motor',
            'description' => 'Motor parou de funcionar e não liga.',
            'priority' => 'high',
        ]);

        $response->assertCreated();
        $ticketId = $response->json('data.id');

        $ticket = PortalTicket::find($ticketId);
        $this->assertNotNull($ticket->sla_due_at, 'SLA Due At deve ser preenchido');
        $this->assertTrue(now()->addMinutes(480)->diffInMinutes($ticket->sla_due_at) <= 1, 'SLA Due At difere de 480 minutos em tempo real');
    }

    public function test_ticket_pauses_sla_when_status_is_waiting_customer_and_resumes_on_progress(): void
    {
        // Setup initial ticket
        $dueAt = now()->addMinutes(120);
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subject' => 'Ticket de Teste de SLA',
            'description' => 'Testando...',
            'priority' => 'high',
            'status' => 'in_progress',
            'ticket_number' => 'TKT-000999',
            'sla_due_at' => $dueAt->format('Y-m-d H:i:s'),
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $service = app(HelpdeskService::class);

        // Muda para waiting_customer (deve preencher o paused_at com o now atual)
        $service->changeTicketStatus($ticketId, 'waiting_customer', $this->admin->id);

        $ticket = PortalTicket::find($ticketId);
        $this->assertEquals('waiting_customer', $ticket->status);
        $this->assertNotNull($ticket->paused_at);
        $this->assertTrue(now()->diffInMinutes($ticket->paused_at) <= 1);

        // Avançamos o tempo em 30 minutos (Isso simula que o cliente demorou 30 min pra responder)
        $this->travel(30)->minutes();

        // O cliente respondeu e o status voltou para in_progress
        $service->changeTicketStatus($ticketId, 'in_progress', $this->admin->id);

        $ticket = PortalTicket::find($ticketId);
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNull($ticket->paused_at); // Foi esvaziado

        // A SLA anterior que estava a 120min agora DEVE ser empurrada para frente em 30 min
        $expectedNewDueAt = $dueAt->copy()->addMinutes(30);
        $this->assertTrue($expectedNewDueAt->diffInMinutes($ticket->sla_due_at) <= 1, 'O prazo de SLA não foi empurrado corretamente pelos minutos pausados.');
    }

    public function test_closed_tickets_cannot_receive_messages(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subject' => 'Ticket Resolvido',
            'description' => 'Testando...',
            'priority' => 'high',
            'status' => 'closed',
            'ticket_number' => 'TKT-001000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->portalUser, ['portal:access']);
        app()->instance('current_tenant_id', $this->tenant->id);

        $response = $this->postJson("/api/v1/portal/tickets/{$ticketId}/messages", [
            'message' => 'Tentativa de responder em ticket fechado.',
        ]);

        $response->assertStatus(422);
    }

    public function test_reopened_ticket_clears_resolved_at_timestamp(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subject' => 'Ticket Fechado',
            'description' => 'Testando...',
            'status' => 'resolved',
            'resolved_at' => now()->format('Y-m-d H:i:s'),
            'ticket_number' => 'TKT-001001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(HelpdeskService::class);
        $service->changeTicketStatus($ticketId, 'reopened', $this->admin->id);

        $ticket = PortalTicket::find($ticketId);
        $this->assertEquals('reopened', $ticket->status);
        $this->assertNull($ticket->resolved_at, 'O timestamp resolved_at deve ser anulado quando o ticket for reaberto.');
    }
}
