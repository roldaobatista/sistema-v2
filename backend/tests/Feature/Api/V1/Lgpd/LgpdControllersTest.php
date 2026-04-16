<?php

namespace Tests\Feature\Api\V1\Lgpd;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\LgpdConsentLog;
use App\Models\LgpdDataRequest;
use App\Models\LgpdDataTreatment;
use App\Models\LgpdDpoConfig;
use App\Models\LgpdSecurityIncident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LgpdControllersTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        Sanctum::actingAs($this->user, ['*']);
    }

    // =========================================================================
    // DATA TREATMENT CONTROLLER
    // =========================================================================

    public function test_treatment_index_returns_paginated_treatments(): void
    {
        LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Dados Pessoais',
            'purpose' => 'Cadastro de clientes',
            'legal_basis' => 'consent',
            'data_types' => 'nome, email, telefone',
            'created_by' => $this->user->id,
        ]);

        LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Dados Financeiros',
            'purpose' => 'Faturamento',
            'legal_basis' => 'contract_execution',
            'data_types' => 'CPF, banco, conta',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/treatments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'tenant_id', 'data_category', 'purpose', 'legal_basis', 'data_types'],
                ],
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_treatment_index_filters_by_legal_basis(): void
    {
        LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Cat A',
            'purpose' => 'Purpose A',
            'legal_basis' => 'consent',
            'data_types' => 'tipo A',
            'created_by' => $this->user->id,
        ]);

        LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Cat B',
            'purpose' => 'Purpose B',
            'legal_basis' => 'legal_obligation',
            'data_types' => 'tipo B',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/treatments?legal_basis=consent');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.legal_basis', 'consent');
    }

    public function test_treatment_store_creates_with_correct_tenant(): void
    {
        $payload = [
            'data_category' => 'Dados Sensíveis',
            'purpose' => 'Saúde ocupacional',
            'legal_basis' => 'legal_obligation',
            'data_types' => 'atestados, exames',
            'description' => 'Tratamento de dados de saúde',
            'retention_period' => '20 anos',
            'retention_legal_basis' => 'CLT Art. 168',
        ];

        $response = $this->postJson('/api/v1/lgpd/treatments', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'data_category' => 'Dados Sensíveis',
                'purpose' => 'Saúde ocupacional',
                'legal_basis' => 'legal_obligation',
                'tenant_id' => $this->tenant->id,
            ]);

        $this->assertDatabaseHas('lgpd_data_treatments', [
            'data_category' => 'Dados Sensíveis',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_treatment_show_returns_treatment(): void
    {
        $treatment = LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Dados Pessoais',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'data_types' => 'email',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/treatments/{$treatment->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $treatment->id,
                'data_category' => 'Dados Pessoais',
                'purpose' => 'Marketing',
            ]);
    }

    public function test_treatment_update_modifies_treatment(): void
    {
        $treatment = LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Original',
            'purpose' => 'Original purpose',
            'legal_basis' => 'consent',
            'data_types' => 'email',
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/lgpd/treatments/{$treatment->id}", [
            'data_category' => 'Atualizado',
            'purpose' => 'Updated purpose',
            'legal_basis' => 'legitimate_interest',
            'data_types' => 'email, nome',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'data_category' => 'Atualizado',
                'purpose' => 'Updated purpose',
                'legal_basis' => 'legitimate_interest',
            ]);

        $this->assertDatabaseHas('lgpd_data_treatments', [
            'id' => $treatment->id,
            'data_category' => 'Atualizado',
        ]);
    }

    public function test_treatment_destroy_deletes_treatment(): void
    {
        $treatment = LgpdDataTreatment::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => 'To Delete',
            'purpose' => 'Test',
            'legal_basis' => 'consent',
            'data_types' => 'email',
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/lgpd/treatments/{$treatment->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('lgpd_data_treatments', [
            'id' => $treatment->id,
        ]);
    }

    public function test_treatment_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/treatments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['data_category', 'purpose', 'legal_basis', 'data_types']);
    }

    public function test_treatment_store_validates_legal_basis_enum(): void
    {
        $response = $this->postJson('/api/v1/lgpd/treatments', [
            'data_category' => 'Test',
            'purpose' => 'Test',
            'legal_basis' => 'invalid_basis',
            'data_types' => 'email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['legal_basis']);
    }

    public function test_treatment_cross_tenant_isolation(): void
    {
        $otherTreatment = LgpdDataTreatment::create([
            'tenant_id' => $this->otherTenant->id,
            'data_category' => 'Other Tenant Data',
            'purpose' => 'Other purpose',
            'legal_basis' => 'consent',
            'data_types' => 'email',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/treatments/{$otherTreatment->id}");

        $response->assertNotFound();
    }

    // =========================================================================
    // CONSENT LOG CONTROLLER
    // =========================================================================

    public function test_consent_index_returns_paginated_consents(): void
    {
        LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'João Silva',
            'holder_email' => 'joao@example.com',
            'holder_document' => '12345678900',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Maria Santos',
            'holder_email' => 'maria@example.com',
            'purpose' => 'Newsletter',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/v1/lgpd/consents');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'tenant_id', 'holder_name', 'purpose', 'status', 'granted_at'],
                ],
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_consent_index_filters_by_status(): void
    {
        LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Active Consent',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Revoked Consent',
            'purpose' => 'Newsletter',
            'legal_basis' => 'consent',
            'status' => 'revoked',
            'granted_at' => now(),
            'revoked_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/v1/lgpd/consents?status=granted');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'granted');
    }

    public function test_consent_store_creates_with_granted_status_and_captures_ip(): void
    {
        $payload = [
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Test Holder',
            'holder_email' => 'test@example.com',
            'holder_document' => '12345678900',
            'purpose' => 'Marketing direto',
            'legal_basis' => 'consent',
        ];

        $response = $this->postJson('/api/v1/lgpd/consents', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'holder_name' => 'Test Holder',
                'status' => 'granted',
                'tenant_id' => $this->tenant->id,
            ]);

        $this->assertDatabaseHas('lgpd_consent_logs', [
            'holder_name' => 'Test Holder',
            'status' => 'granted',
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify ip_address and granted_at were set
        $consent = LgpdConsentLog::where('holder_name', 'Test Holder')->first();
        $this->assertNotNull($consent->ip_address);
        $this->assertNotNull($consent->granted_at);
    }

    public function test_consent_show_returns_consent(): void
    {
        $consent = LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Show Test',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson("/api/v1/lgpd/consents/{$consent->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $consent->id,
                'holder_name' => 'Show Test',
            ]);
    }

    public function test_consent_revoke_changes_status_to_revoked(): void
    {
        $consent = LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Revoke Test',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson("/api/v1/lgpd/consents/{$consent->id}/revoke", [
            'reason' => 'Não desejo mais receber comunicações',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'revoked',
            ]);

        $consent->refresh();
        $this->assertEquals('revoked', $consent->status);
        $this->assertNotNull($consent->revoked_at);
        $this->assertEquals('Não desejo mais receber comunicações', $consent->revocation_reason);
    }

    public function test_consent_revoke_already_revoked_returns_422(): void
    {
        $consent = LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Already Revoked',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'revoked',
            'granted_at' => now(),
            'revoked_at' => now(),
            'revocation_reason' => 'Already revoked',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson("/api/v1/lgpd/consents/{$consent->id}/revoke", [
            'reason' => 'Tentativa dupla',
        ]);

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Consentimento já revogado.']);
    }

    public function test_consent_revoke_requires_reason(): void
    {
        $consent = LgpdConsentLog::create([
            'tenant_id' => $this->tenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Reason Required',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson("/api/v1/lgpd/consents/{$consent->id}/revoke", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_consent_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/consents', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['holder_type', 'holder_id', 'holder_name', 'purpose', 'legal_basis']);
    }

    public function test_consent_cross_tenant_isolation(): void
    {
        $otherConsent = LgpdConsentLog::create([
            'tenant_id' => $this->otherTenant->id,
            'holder_type' => 'App\\Models\\User',
            'holder_id' => $this->user->id,
            'holder_name' => 'Other Tenant',
            'purpose' => 'Marketing',
            'legal_basis' => 'consent',
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson("/api/v1/lgpd/consents/{$otherConsent->id}");

        $response->assertNotFound();
    }

    // =========================================================================
    // DATA REQUEST CONTROLLER
    // =========================================================================

    public function test_data_request_index_returns_paginated_requests(): void
    {
        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0001',
            'holder_name' => 'João Silva',
            'holder_email' => 'joao@example.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0002',
            'holder_name' => 'Maria Santos',
            'holder_email' => 'maria@example.com',
            'holder_document' => '98765432100',
            'request_type' => LgpdDataRequest::TYPE_DELETION,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/requests');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'tenant_id', 'protocol', 'holder_name', 'request_type', 'status', 'deadline'],
                ],
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_data_request_index_filters_by_status(): void
    {
        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0010',
            'holder_name' => 'Pending',
            'holder_email' => 'p@test.com',
            'holder_document' => '111',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0011',
            'holder_name' => 'Completed',
            'holder_email' => 'c@test.com',
            'holder_document' => '222',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_COMPLETED,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/requests?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_data_request_store_creates_with_auto_protocol_and_deadline(): void
    {
        Mail::fake();

        $payload = [
            'holder_name' => 'Carlos Souza',
            'holder_email' => 'carlos@example.com',
            'holder_document' => '11122233344',
            'request_type' => 'access',
            'description' => 'Quero acessar meus dados pessoais',
        ];

        $response = $this->postJson('/api/v1/lgpd/requests', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'holder_name' => 'Carlos Souza',
                'request_type' => 'access',
                'status' => 'pending',
                'tenant_id' => $this->tenant->id,
            ]);

        // Verify auto-generated protocol
        $dataRequest = LgpdDataRequest::where('holder_name', 'Carlos Souza')->first();
        $this->assertNotNull($dataRequest->protocol);
        $this->assertStringStartsWith('LGPD-', $dataRequest->protocol);

        // Verify deadline was set (15 weekdays from now)
        $this->assertNotNull($dataRequest->deadline);

        // Verify created_by was set
        $this->assertEquals($this->user->id, $dataRequest->created_by);
    }

    public function test_data_request_show_returns_request(): void
    {
        $dataRequest = LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0100',
            'holder_name' => 'Show Test',
            'holder_email' => 'show@test.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_PORTABILITY,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/requests/{$dataRequest->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $dataRequest->id,
                'protocol' => 'LGPD-2026-0100',
                'holder_name' => 'Show Test',
                'request_type' => 'portability',
            ]);
    }

    public function test_data_request_respond_updates_status(): void
    {
        $dataRequest = LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0200',
            'holder_name' => 'Respond Test',
            'holder_email' => 'respond@test.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/lgpd/requests/{$dataRequest->id}/respond", [
            'status' => 'completed',
            'response_notes' => 'Dados enviados ao titular por email.',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'completed',
                'response_notes' => 'Dados enviados ao titular por email.',
            ]);

        $dataRequest->refresh();
        $this->assertEquals('completed', $dataRequest->status);
        $this->assertNotNull($dataRequest->responded_at);
        $this->assertEquals($this->user->id, $dataRequest->responded_by);
    }

    public function test_data_request_respond_already_completed_returns_422(): void
    {
        $dataRequest = LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0300',
            'holder_name' => 'Already Done',
            'holder_email' => 'done@test.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_COMPLETED,
            'deadline' => now()->addWeekdays(15),
            'response_notes' => 'Already responded',
            'responded_at' => now(),
            'responded_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/lgpd/requests/{$dataRequest->id}/respond", [
            'status' => 'completed',
            'response_notes' => 'Tentativa dupla',
        ]);

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Solicitação já respondida.']);
    }

    public function test_data_request_respond_can_deny(): void
    {
        $dataRequest = LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0350',
            'holder_name' => 'Deny Test',
            'holder_email' => 'deny@test.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_DELETION,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/lgpd/requests/{$dataRequest->id}/respond", [
            'status' => 'denied',
            'response_notes' => 'Dados necessários por obrigação legal (CLT).',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'denied',
            ]);
    }

    public function test_data_request_overdue_returns_only_overdue_requests(): void
    {
        // Overdue request (deadline in the past)
        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0400',
            'holder_name' => 'Overdue Request',
            'holder_email' => 'overdue@test.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->subDays(5),
            'created_by' => $this->user->id,
        ]);

        // Not overdue (deadline in the future)
        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0401',
            'holder_name' => 'On Time Request',
            'holder_email' => 'ontime@test.com',
            'holder_document' => '98765432100',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addDays(10),
            'created_by' => $this->user->id,
        ]);

        // Completed (should not appear even if deadline passed)
        LgpdDataRequest::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'LGPD-2026-0402',
            'holder_name' => 'Completed Past Deadline',
            'holder_email' => 'completed@test.com',
            'holder_document' => '55566677788',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_COMPLETED,
            'deadline' => now()->subDays(3),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/requests/overdue');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.holder_name', 'Overdue Request');
    }

    public function test_data_request_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/requests', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['holder_name', 'holder_email', 'holder_document', 'request_type']);
    }

    public function test_data_request_store_validates_request_type_enum(): void
    {
        $response = $this->postJson('/api/v1/lgpd/requests', [
            'holder_name' => 'Test',
            'holder_email' => 'test@test.com',
            'holder_document' => '12345678900',
            'request_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['request_type']);
    }

    public function test_data_request_cross_tenant_isolation(): void
    {
        $otherRequest = LgpdDataRequest::create([
            'tenant_id' => $this->otherTenant->id,
            'protocol' => 'LGPD-2026-9999',
            'holder_name' => 'Other Tenant Request',
            'holder_email' => 'other@test.com',
            'holder_document' => '12345678900',
            'request_type' => LgpdDataRequest::TYPE_ACCESS,
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/requests/{$otherRequest->id}");

        $response->assertNotFound();
    }

    // =========================================================================
    // DPO CONFIG CONTROLLER
    // =========================================================================

    public function test_dpo_show_returns_config(): void
    {
        LgpdDpoConfig::create([
            'tenant_id' => $this->tenant->id,
            'dpo_name' => 'Ana DPO',
            'dpo_email' => 'dpo@empresa.com',
            'dpo_phone' => '11999998888',
            'is_public' => true,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/dpo');

        $response->assertOk()
            ->assertJsonFragment([
                'dpo_name' => 'Ana DPO',
                'dpo_email' => 'dpo@empresa.com',
                'dpo_phone' => '11999998888',
                'tenant_id' => $this->tenant->id,
            ]);
    }

    public function test_dpo_show_returns_404_when_not_configured(): void
    {
        $response = $this->getJson('/api/v1/lgpd/dpo');

        $response->assertNotFound()
            ->assertJsonFragment(['message' => 'DPO não configurado.']);
    }

    public function test_dpo_upsert_creates_new_config(): void
    {
        $payload = [
            'dpo_name' => 'Novo DPO',
            'dpo_email' => 'novo.dpo@empresa.com',
            'dpo_phone' => '11888887777',
            'is_public' => true,
        ];

        $response = $this->putJson('/api/v1/lgpd/dpo', $payload);

        $response->assertOk()
            ->assertJsonFragment([
                'dpo_name' => 'Novo DPO',
                'dpo_email' => 'novo.dpo@empresa.com',
                'tenant_id' => $this->tenant->id,
            ]);

        $this->assertDatabaseHas('lgpd_dpo_configs', [
            'dpo_name' => 'Novo DPO',
            'tenant_id' => $this->tenant->id,
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_dpo_upsert_updates_existing_config(): void
    {
        LgpdDpoConfig::create([
            'tenant_id' => $this->tenant->id,
            'dpo_name' => 'DPO Original',
            'dpo_email' => 'original@empresa.com',
            'updated_by' => $this->user->id,
        ]);

        $response = $this->putJson('/api/v1/lgpd/dpo', [
            'dpo_name' => 'DPO Atualizado',
            'dpo_email' => 'atualizado@empresa.com',
            'dpo_phone' => '11777776666',
            'is_public' => false,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'dpo_name' => 'DPO Atualizado',
                'dpo_email' => 'atualizado@empresa.com',
            ]);

        // Ensure only one record exists for this tenant
        $this->assertEquals(1, LgpdDpoConfig::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_dpo_upsert_validates_required_fields(): void
    {
        $response = $this->putJson('/api/v1/lgpd/dpo', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dpo_name', 'dpo_email']);
    }

    public function test_dpo_upsert_validates_email_format(): void
    {
        $response = $this->putJson('/api/v1/lgpd/dpo', [
            'dpo_name' => 'Test DPO',
            'dpo_email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dpo_email']);
    }

    // =========================================================================
    // SECURITY INCIDENT CONTROLLER
    // =========================================================================

    public function test_incident_index_returns_paginated_incidents(): void
    {
        LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0001',
            'severity' => 'high',
            'description' => 'Vazamento de dados',
            'affected_data' => 'emails, CPFs',
            'affected_holders_count' => 150,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0002',
            'severity' => 'low',
            'description' => 'Acesso indevido',
            'affected_data' => 'nomes',
            'affected_holders_count' => 5,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_INVESTIGATING,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/incidents');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'tenant_id', 'protocol', 'severity', 'description', 'status', 'detected_at'],
                ],
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_incident_index_filters_by_severity(): void
    {
        LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0010',
            'severity' => 'critical',
            'description' => 'Critical incident',
            'affected_data' => 'all data',
            'affected_holders_count' => 1000,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0011',
            'severity' => 'low',
            'description' => 'Low incident',
            'affected_data' => 'names',
            'affected_holders_count' => 1,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/incidents?severity=critical');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.severity', 'critical');
    }

    public function test_incident_store_creates_with_auto_protocol(): void
    {
        $payload = [
            'severity' => 'high',
            'description' => 'Ransomware detectado no servidor de backup',
            'affected_data' => 'Dados cadastrais, financeiros',
            'affected_holders_count' => 500,
            'measures_taken' => 'Servidor isolado da rede',
            'detected_at' => '2026-04-01 14:30:00',
        ];

        $response = $this->postJson('/api/v1/lgpd/incidents', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'severity' => 'high',
                'status' => 'open',
                'tenant_id' => $this->tenant->id,
                'affected_holders_count' => 500,
            ]);

        // Verify auto-generated protocol
        $incident = LgpdSecurityIncident::where('description', 'like', 'Ransomware%')->first();
        $this->assertNotNull($incident->protocol);
        $this->assertStringStartsWith('INC-', $incident->protocol);
        $this->assertEquals($this->user->id, $incident->reported_by);
    }

    public function test_incident_show_returns_incident(): void
    {
        $incident = LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0100',
            'severity' => 'medium',
            'description' => 'Show test incident',
            'affected_data' => 'emails',
            'affected_holders_count' => 10,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/incidents/{$incident->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $incident->id,
                'protocol' => 'INC-2026-0100',
                'description' => 'Show test incident',
            ]);
    }

    public function test_incident_update_modifies_status(): void
    {
        $incident = LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0200',
            'severity' => 'high',
            'description' => 'Update test',
            'affected_data' => 'CPFs',
            'affected_holders_count' => 50,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/lgpd/incidents/{$incident->id}", [
            'status' => 'investigating',
            'measures_taken' => 'Equipe de resposta ativada',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'investigating',
                'measures_taken' => 'Equipe de resposta ativada',
            ]);
    }

    public function test_incident_update_sets_holders_notified_at_when_notified(): void
    {
        $incident = LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0250',
            'severity' => 'high',
            'description' => 'Notification test',
            'affected_data' => 'data',
            'affected_holders_count' => 100,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_INVESTIGATING,
            'holders_notified' => false,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/lgpd/incidents/{$incident->id}", [
            'holders_notified' => true,
        ]);

        $response->assertOk();

        $incident->refresh();
        $this->assertTrue($incident->holders_notified);
        $this->assertNotNull($incident->holders_notified_at);
    }

    public function test_incident_anpd_report_generates_report(): void
    {
        $incident = LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0300',
            'severity' => 'critical',
            'description' => 'Major data breach',
            'affected_data' => 'All personal data',
            'affected_holders_count' => 1000,
            'measures_taken' => 'Systems shut down',
            'detected_at' => now()->subDays(2),
            'status' => LgpdSecurityIncident::STATUS_CONTAINED,
            'holders_notified' => true,
            'holders_notified_at' => now()->subDay(),
            'reported_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/incidents/{$incident->id}/anpd-report");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'report' => [
                        'protocolo',
                        'data_deteccao',
                        'severidade',
                        'descricao',
                        'dados_afetados',
                        'titulares_afetados',
                        'medidas_adotadas',
                        'titulares_notificados',
                        'data_notificacao_titulares',
                        'status',
                        'gerado_em',
                    ],
                ],
            ])
            ->assertJsonPath('data.report.protocolo', 'INC-2026-0300')
            ->assertJsonPath('data.report.severidade', 'critical')
            ->assertJsonPath('data.report.titulares_afetados', 1000)
            ->assertJsonPath('data.report.titulares_notificados', 'Sim');

        // Verify anpd_reported_at was set
        $incident->refresh();
        $this->assertNotNull($incident->anpd_reported_at);
    }

    public function test_incident_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/incidents', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['severity', 'description', 'affected_data', 'affected_holders_count', 'detected_at']);
    }

    public function test_incident_store_validates_severity_enum(): void
    {
        $response = $this->postJson('/api/v1/lgpd/incidents', [
            'severity' => 'ultra_critical',
            'description' => 'Test',
            'affected_data' => 'data',
            'affected_holders_count' => 1,
            'detected_at' => '2026-04-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['severity']);
    }

    public function test_incident_update_validates_status_enum(): void
    {
        $incident = LgpdSecurityIncident::create([
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-2026-0400',
            'severity' => 'low',
            'description' => 'Validate test',
            'affected_data' => 'names',
            'affected_holders_count' => 1,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/lgpd/incidents/{$incident->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_incident_cross_tenant_isolation(): void
    {
        $otherIncident = LgpdSecurityIncident::create([
            'tenant_id' => $this->otherTenant->id,
            'protocol' => 'INC-2026-9999',
            'severity' => 'high',
            'description' => 'Other tenant incident',
            'affected_data' => 'data',
            'affected_holders_count' => 10,
            'detected_at' => now(),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/incidents/{$otherIncident->id}");

        $response->assertNotFound();
    }
}
