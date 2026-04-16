<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\FiscalAutomationService;
use App\Services\Fiscal\FiscalComplianceService;
use App\Services\Fiscal\FiscalFinanceService;
use App\Services\Fiscal\FiscalTemplateService;
use App\Services\Fiscal\FiscalWebhookService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiscalExpandedControllerTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createNote(array $overrides = []): FiscalNote
    {
        return FiscalNote::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ], $overrides));
    }

    // ─── WEBHOOKS ──────────────────────────────────────────────────

    public function test_list_webhooks_returns_data(): void
    {
        $mock = $this->mock(FiscalWebhookService::class);
        $mock->shouldReceive('listForTenant')
            ->with($this->tenant->id)
            ->once()
            ->andReturn([]);

        $response = $this->getJson('/api/v1/fiscal/webhooks');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_create_webhook_succeeds(): void
    {
        $mock = $this->mock(FiscalWebhookService::class);
        $mock->shouldReceive('createWebhook')
            ->once()
            ->andReturn(['id' => 1, 'url' => 'https://example.com/hook', 'events' => ['nota.autorizada']]);

        $response = $this->postJson('/api/v1/fiscal/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['nota.autorizada'],
        ]);

        $response->assertStatus(201);
    }

    public function test_delete_webhook_succeeds(): void
    {
        $mock = $this->mock(FiscalWebhookService::class);
        $mock->shouldReceive('deleteWebhook')
            ->with(1, $this->tenant->id)
            ->once()
            ->andReturn(true);

        $response = $this->deleteJson('/api/v1/fiscal/webhooks/1');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Webhook removido');
    }

    public function test_delete_webhook_returns_404_when_not_found(): void
    {
        $mock = $this->mock(FiscalWebhookService::class);
        $mock->shouldReceive('deleteWebhook')
            ->once()
            ->andReturn(false);

        $response = $this->deleteJson('/api/v1/fiscal/webhooks/999');

        $response->assertStatus(404);
    }

    // ─── TEMPLATES ─────────────────────────────────────────────────

    public function test_list_templates_returns_data(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('listTemplates')
            ->with($this->tenant->id)
            ->once()
            ->andReturn([]);

        $response = $this->getJson('/api/v1/fiscal/templates');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_save_template_creates_record(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('saveTemplate')
            ->once()
            ->andReturn(['id' => 1, 'name' => 'Template A', 'type' => 'nfe']);

        $response = $this->postJson('/api/v1/fiscal/templates', [
            'name' => 'Template A',
            'type' => 'nfe',
            'template_data' => ['nature_of_operation' => 'Venda'],
        ]);

        $response->assertStatus(201);
    }

    public function test_apply_template_returns_data(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('applyTemplate')
            ->with(1, $this->tenant->id)
            ->once()
            ->andReturn(['nature_of_operation' => 'Venda']);

        $response = $this->getJson('/api/v1/fiscal/templates/1/apply');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_apply_template_returns_404_when_not_found(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('applyTemplate')
            ->once()
            ->andReturn(null);

        $response = $this->getJson('/api/v1/fiscal/templates/999/apply');

        $response->assertStatus(404);
    }

    public function test_delete_template_succeeds(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('deleteTemplate')
            ->with(1, $this->tenant->id)
            ->once()
            ->andReturn(true);

        $response = $this->deleteJson('/api/v1/fiscal/templates/1');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Template removido');
    }

    // ─── COMPLIANCE ────────────────────────────────────────────────

    public function test_certificate_alert_returns_data(): void
    {
        $mock = $this->mock(FiscalComplianceService::class);
        $mock->shouldReceive('checkCertificateExpiry')
            ->once()
            ->andReturn(['days_to_expire' => 30, 'status' => 'valid']);

        $response = $this->getJson('/api/v1/fiscal/certificate-alert');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_validate_document_cpf(): void
    {
        $mock = $this->mock(FiscalComplianceService::class);
        $mock->shouldReceive('validateDocument')
            ->once()
            ->andReturn(['valid' => true, 'type' => 'cpf', 'formatted' => '123.456.789-09']);

        $response = $this->postJson('/api/v1/fiscal/validate-document', [
            'documento' => '12345678909',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true);
    }

    public function test_audit_report_returns_data(): void
    {
        $mock = $this->mock(FiscalComplianceService::class);
        $mock->shouldReceive('auditReport')
            ->once()
            ->andReturn(['total' => 0, 'entries' => []]);

        $response = $this->getJson('/api/v1/fiscal/audit-report?from=2026-01-01&to=2026-03-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_audit_log_for_note(): void
    {
        $note = $this->createNote(['status' => 'authorized']);

        $mock = $this->mock(FiscalComplianceService::class);
        $mock->shouldReceive('getAuditLog')
            ->with($note->id, $this->tenant->id)
            ->once()
            ->andReturn([]);

        $response = $this->getJson("/api/v1/fiscal/notas/{$note->id}/audit");

        $response->assertStatus(200);
    }

    // ─── FINANCE ───────────────────────────────────────────────────

    public function test_reconcile_note_calls_service(): void
    {
        $note = $this->createNote(['status' => 'authorized']);

        $mock = $this->mock(FiscalFinanceService::class);
        $mock->shouldReceive('reconcileWithReceivables')
            ->once()
            ->andReturn(['reconciled' => true]);

        $response = $this->postJson("/api/v1/fiscal/notas/{$note->id}/reconcile");

        $response->assertStatus(200)
            ->assertJsonPath('data.reconciled', true);
    }

    public function test_generate_boleto_calls_service(): void
    {
        $note = $this->createNote(['status' => 'authorized']);

        $mock = $this->mock(FiscalFinanceService::class);
        $mock->shouldReceive('generateBoletoData')
            ->once()
            ->andReturn(['barcode' => '12345']);

        $response = $this->postJson("/api/v1/fiscal/notas/{$note->id}/boleto");

        $response->assertStatus(200)
            ->assertJsonPath('data.barcode', '12345');
    }

    public function test_duplicate_note_returns_data(): void
    {
        $note = $this->createNote(['status' => 'authorized']);

        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('duplicateNote')
            ->once()
            ->andReturn(['type' => 'nfe', 'total_amount' => 500.00]);

        $response = $this->getJson("/api/v1/fiscal/notas/{$note->id}/duplicate");

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_search_by_access_key_found(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('searchByAccessKey')
            ->once()
            ->andReturn(['id' => 1, 'access_key' => str_repeat('1', 44)]);

        $response = $this->getJson('/api/v1/fiscal/search-key?chave='.str_repeat('1', 44));

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_search_by_access_key_not_found(): void
    {
        $mock = $this->mock(FiscalTemplateService::class);
        $mock->shouldReceive('searchByAccessKey')
            ->once()
            ->andReturn(null);

        $response = $this->getJson('/api/v1/fiscal/search-key?chave='.str_repeat('0', 44));

        $response->assertStatus(404);
    }

    // ─── RETRY EMAIL ───────────────────────────────────────────────

    public function test_retry_email_calls_automation_service(): void
    {
        $note = $this->createNote(['status' => 'authorized']);

        $mock = $this->mock(FiscalAutomationService::class);
        $mock->shouldReceive('retryEmail')
            ->once()
            ->andReturn(['sent' => true]);

        $response = $this->postJson("/api/v1/fiscal/notas/{$note->id}/retry-email");

        $response->assertStatus(200)
            ->assertJsonPath('data.sent', true);
    }

    // ─── TENANT ISOLATION ──────────────────────────────────────────

    public function test_retry_email_404_for_other_tenant_note(): void
    {
        $otherTenant = Tenant::factory()->create();
        $note = FiscalNote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'status' => 'authorized',
        ]);

        $response = $this->postJson("/api/v1/fiscal/notas/{$note->id}/retry-email");

        $response->assertStatus(404);
    }
}
