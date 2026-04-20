<?php

namespace Tests\Feature;

use App\Events\QuoteApproved;
use App\Models\AccountReceivable;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    private ClientPortalUser $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->portalUser = ClientPortalUser::forceCreate([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal Cliente',
            'email' => 'portal@example.com',
            'password' => 'senha12345',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->portalUser, ['portal:access']);
    }

    public function test_portal_reject_quote_uses_supported_columns(): void
    {
        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $response = $this->putJson("/api/v1/portal/quotes/{$quote->id}/status", [
            'action' => 'reject',
            'comments' => 'Cliente optou por adiar.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Cliente optou por adiar.');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'rejected',
            'rejection_reason' => 'Cliente optou por adiar.',
        ]);
    }

    public function test_portal_reject_quote_allows_empty_comments(): void
    {
        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $response = $this->putJson("/api/v1/portal/quotes/{$quote->id}/status", [
            'action' => 'reject',
            'comments' => '',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'rejected',
            'rejection_reason' => null,
        ]);
    }

    public function test_portal_approve_quote_dispatches_quote_approved_event(): void
    {
        Event::fake([QuoteApproved::class]);

        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $this->putJson("/api/v1/portal/quotes/{$quote->id}/status", [
            'action' => 'approve',
            'comments' => 'Aprovado pelo portal do cliente.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_APPROVED,
            'approval_channel' => 'portal',
            'approved_by_name' => 'Portal Cliente',
            'approval_notes' => 'Aprovado pelo portal do cliente.',
        ]);

        Event::assertDispatched(QuoteApproved::class, function (QuoteApproved $event) use ($quote, $seller) {
            return $event->quote->id === $quote->id
                && $event->user->id === $seller->id
                && $event->quote->approval_channel === 'portal'
                && $event->quote->approved_by_name === 'Portal Cliente';
        });
    }

    public function test_portal_reject_quote_writes_audit_context(): void
    {
        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $this->putJson("/api/v1/portal/quotes/{$quote->id}/status", [
            'action' => 'reject',
            'comments' => 'Cliente solicitou revisao comercial.',
        ])->assertOk();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_REJECTED,
            'approval_channel' => 'portal',
            'approved_by_name' => 'Portal Cliente',
            'approval_notes' => 'Cliente solicitou revisao comercial.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'auditable_type' => Quote::class,
            'auditable_id' => $quote->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_portal_new_service_call_uses_valid_schema_and_links_equipment(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/portal/service-calls', [
            'equipment_id' => $equipment->id,
            'description' => 'Chamado aberto pelo cliente no portal para verificacao tecnica.',
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', ServiceCall::STATUS_PENDING_SCHEDULING);

        $serviceCallId = $response->json('data.id');

        $this->assertDatabaseHas('service_calls', [
            'id' => $serviceCallId,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'priority' => 'high',
            'status' => ServiceCall::STATUS_PENDING_SCHEDULING,
            'observations' => 'Chamado aberto pelo cliente no portal para verificacao tecnica.',
        ]);

        $this->assertDatabaseHas('service_call_equipments', [
            'service_call_id' => $serviceCallId,
            'equipment_id' => $equipment->id,
        ]);

        $this->assertStringStartsWith('CT-', ServiceCall::findOrFail($serviceCallId)->call_number);
    }

    public function test_cannot_approve_expired_quote_via_portal(): void
    {

        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => now()->subDay(),
        ]);

        $this->putJson("/api/v1/portal/quotes/{$quote->id}/status", [
            'action' => 'approve',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Este orcamento esta expirado.');
    }

    public function test_portal_me_rejects_non_portal_actor(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/portal/me')->assertForbidden();
    }

    public function test_portal_tickets_reject_non_portal_actor(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/portal/tickets')->assertForbidden();
    }

    public function test_portal_equipment_returns_only_customer_equipment(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'brand' => 'Marca A',
            'model' => 'Modelo 1',
            'serial_number' => 'SER-001',
        ]);

        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'brand' => 'Marca B',
            'model' => 'Modelo 2',
            'serial_number' => 'SER-999',
        ]);

        $this->getJson('/api/v1/portal/equipment')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.serial_number', 'SER-001');
    }

    public function test_portal_financials_include_overdue_titles_and_exclude_paid(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 1000.00,
            'amount_paid' => 200.00,
            'status' => 'overdue',
            'due_date' => now()->subDays(10),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 500.00,
            'amount_paid' => 100.00,
            'status' => 'partial',
            'due_date' => now()->addDays(5),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 300.00,
            'amount_paid' => 300.00,
            'status' => 'paid',
            'due_date' => now()->subDays(20),
        ]);

        $this->getJson('/api/v1/portal/financials')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'overdue')
            ->assertJsonPath('data.1.status', 'partial');
    }

    public function test_portal_certificates_endpoint_returns_success(): void
    {
        if (Schema::hasTable('calibration_certificates')) {
            $equipment = Equipment::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
            ]);

            DB::table('calibration_certificates')->insert([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'equipment_id' => $equipment->id,
                'number' => 'CERT-001',
                'issued_at' => now()->subDays(10),
                'valid_until' => now()->addDays(20),
                'file_path' => 'certificates/cert-001.pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->getJson('/api/v1/portal/certificates')
                ->assertOk()
                ->assertJsonPath('data.0.certificate_number', 'CERT-001')
                ->assertJsonPath('data.0.status', 'expiring_soon');

            return;
        }

        $this->getJson('/api/v1/portal/certificates')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
