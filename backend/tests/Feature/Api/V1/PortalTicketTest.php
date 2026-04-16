<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalTicketTest extends TestCase
{
    private Tenant $tenant;

    private ClientPortalUser $portalUser;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
            EnsurePortalAccess::class,
        ]);

        $this->ensurePortalTablesExist();

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->portalUser = ClientPortalUser::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal Test User',
            'email' => 'portal@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->portalUser, ['portal:access']);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function ensurePortalTablesExist(): void
    {
        if (! Schema::hasTable('portal_tickets')) {
            Schema::create('portal_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('ticket_number')->nullable();
                $table->string('subject');
                $table->text('description');
                $table->string('status')->default('open');
                $table->string('priority')->default('normal');
                $table->string('category')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('portal_ticket_messages')) {
            Schema::create('portal_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('portal_ticket_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('message');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
            });
        }
    }

    // ─── STORE ────────────────────────────────────────

    public function test_store_creates_ticket_with_number(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Problema no equipamento',
            'description' => 'O equipamento X parou de funcionar.',
            'priority' => 'high',
            'category' => 'suporte',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotNull($data['ticket_number']);
        $this->assertStringStartsWith('TKT-', $data['ticket_number']);
        $this->assertEquals('open', $data['status']);
        $this->assertEquals($this->customer->id, $data['customer_id']);
        $this->assertEquals($this->tenant->id, $data['tenant_id']);
    }

    public function test_store_uses_default_priority_when_not_provided(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Dúvida geral',
            'description' => 'Tenho uma dúvida.',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('normal', $response->json('data.priority'));
    }

    public function test_store_fails_without_subject(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', [
            'description' => 'Missing subject',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject']);
    }

    public function test_store_fails_without_description(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Missing description',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_store_rejects_invalid_priority(): void
    {
        $response = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Test',
            'description' => 'Test',
            'priority' => 'super_urgent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    // ─── INDEX ────────────────────────────────────────

    public function test_index_returns_only_own_tickets(): void
    {
        // Create ticket for our user
        DB::table('portal_tickets')->insert([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'My Ticket',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create ticket for another customer in same tenant
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        DB::table('portal_tickets')->insert([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => 999,
            'subject' => 'Other Ticket',
            'description' => 'Not mine',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/portal/tickets');
        $response->assertStatus(200);

        $data = collect($response->json('data'));
        $data->each(function ($ticket) {
            $this->assertEquals($this->customer->id, $ticket['customer_id']);
        });
    }

    public function test_index_filters_by_status(): void
    {
        DB::table('portal_tickets')->insert([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Open Ticket',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000010',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('portal_tickets')->insert([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Resolved Ticket',
            'description' => 'Done',
            'status' => 'resolved',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000011',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/portal/tickets?status=open');
        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $data->each(fn ($t) => $this->assertEquals('open', $t['status']));
    }

    // ─── SHOW ─────────────────────────────────────────

    public function test_show_returns_own_ticket(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'View This',
            'description' => 'Details',
            'status' => 'open',
            'priority' => 'high',
            'ticket_number' => 'TKT-000020',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/portal/tickets/{$ticketId}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['subject' => 'View This']);
    }

    public function test_show_returns_404_for_other_customers_ticket(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => 999,
            'subject' => 'Not Mine',
            'description' => 'Not mine',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000030',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/portal/tickets/{$ticketId}");
        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_ticket(): void
    {
        $response = $this->getJson('/api/v1/portal/tickets/999999');
        $response->assertStatus(404);
    }

    // ─── UPDATE ───────────────────────────────────────

    public function test_update_changes_ticket_status(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Update Me',
            'description' => 'Original',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000040',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/portal/tickets/{$ticketId}", [
            'status' => 'resolved',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('resolved', DB::table('portal_tickets')->find($ticketId)->status);
    }

    public function test_update_sets_resolved_at_when_ticket_is_resolved(): void
    {
        if (! Schema::hasColumn('portal_tickets', 'resolved_at')) {
            Schema::table('portal_tickets', function (Blueprint $table) {
                $table->timestamp('resolved_at')->nullable();
            });
        }

        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Resolve me',
            'description' => 'Original',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000041',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/portal/tickets/{$ticketId}", [
            'status' => 'resolved',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull(DB::table('portal_tickets')->find($ticketId)->resolved_at);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Status Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000050',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/portal/tickets/{$ticketId}", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_update_returns_404_for_other_customer_ticket(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => 999,
            'subject' => 'Not Mine',
            'description' => 'Not mine',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000060',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/portal/tickets/{$ticketId}", [
            'status' => 'resolved',
        ]);
        $response->assertStatus(404);
    }

    // ─── ADD MESSAGE ──────────────────────────────────

    public function test_add_message_to_own_ticket(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Message Test',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000070',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/portal/tickets/{$ticketId}/messages", [
            'message' => 'Ainda preciso de ajuda com isso.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('portal_ticket_messages', [
            'portal_ticket_id' => $ticketId,
            'user_id' => $this->portalUser->id,
            'is_internal' => false,
        ]);
    }

    public function test_add_message_fails_without_message_field(): void
    {
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'No Msg',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000080',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/portal/tickets/{$ticketId}/messages", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_add_message_returns_404_for_other_customer_ticket(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => 999,
            'subject' => 'Other',
            'description' => 'Not mine',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-000090',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/portal/tickets/{$ticketId}/messages", [
            'message' => 'Hacking attempt',
        ]);
        $response->assertStatus(404);
    }

    // ─── TENANT ISOLATION ─────────────────────────────

    public function test_tickets_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $ticketId = DB::table('portal_tickets')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->portalUser->id,
            'subject' => 'Cross-Tenant',
            'description' => 'Should not see',
            'status' => 'open',
            'priority' => 'normal',
            'ticket_number' => 'TKT-900001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/portal/tickets/{$ticketId}");
        $response->assertStatus(404);
    }
}
