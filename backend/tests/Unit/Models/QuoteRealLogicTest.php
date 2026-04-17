<?php

namespace Tests\Unit\Models;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos do Quote model real:
 * canSendToClient(), requiresInternalApproval(), installmentSimulation(),
 * profitMargin(), isExpired(), nextNumber(), approval token/URL, constants, centralSyncData().
 */
class QuoteRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
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

        $this->actingAs($this->user);
    }

    // ── canSendToClient() ──

    public function test_can_send_to_client_when_internally_approved(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::INTERNALLY_APPROVED,
        ]);
        $this->assertTrue($q->canSendToClient());
    }

    public function test_cannot_send_to_client_when_draft(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
        ]);
        $this->assertFalse($q->canSendToClient());
    }

    public function test_cannot_send_to_client_when_sent(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::SENT,
        ]);
        $this->assertFalse($q->canSendToClient());
    }

    // ── requiresInternalApproval() ──

    public function test_requires_internal_approval_when_pending(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::PENDING_INTERNAL_APPROVAL,
        ]);
        $this->assertTrue($q->requiresInternalApproval());
    }

    public function test_does_not_require_internal_approval_when_approved(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::APPROVED,
        ]);
        $this->assertFalse($q->requiresInternalApproval());
    }

    // ── installmentSimulation() ──

    public function test_installment_simulation_returns_5_options(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '12000.00',
        ]);
        $sim = $q->installmentSimulation();

        $this->assertCount(5, $sim);
        $this->assertEquals(2, $sim[0]['installments']);
        $this->assertEquals('6000.00', $sim[0]['value']);
        $this->assertEquals(3, $sim[1]['installments']);
        $this->assertEquals('4000.00', $sim[1]['value']);
        $this->assertEquals(6, $sim[2]['installments']);
        $this->assertEquals('2000.00', $sim[2]['value']);
        $this->assertEquals(10, $sim[3]['installments']);
        $this->assertEquals('1200.00', $sim[3]['value']);
        $this->assertEquals(12, $sim[4]['installments']);
        $this->assertEquals('1000.00', $sim[4]['value']);
    }

    public function test_installment_simulation_zero_total(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
        ]);
        $sim = $q->installmentSimulation();
        foreach ($sim as $option) {
            $this->assertEquals('0.00', $option['value']);
        }
    }

    // ── isExpired() ──

    public function test_is_expired_when_past_valid_until_and_sent(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::SENT,
            'valid_until' => now()->subDays(5),
        ]);
        $this->assertTrue($q->isExpired());
    }

    public function test_is_not_expired_when_future_valid_until(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::SENT,
            'valid_until' => now()->addDays(30),
        ]);
        $this->assertFalse($q->isExpired());
    }

    public function test_is_not_expired_when_approved(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::APPROVED,
            'valid_until' => now()->subDays(5),
        ]);
        $this->assertFalse($q->isExpired());
    }

    public function test_is_not_expired_when_no_valid_until(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::SENT,
            'valid_until' => null,
        ]);
        $this->assertFalse($q->isExpired());
    }

    // ── expirableStatuses() ──

    public function test_expirable_statuses(): void
    {
        $expirable = Quote::expirableStatuses();
        $this->assertContains('sent', $expirable);
        $this->assertContains('pending_internal_approval', $expirable);
        $this->assertContains('internally_approved', $expirable);
        $this->assertNotContains('approved', $expirable);
        $this->assertNotContains('invoiced', $expirable);
    }

    // ── nextNumber() ──

    public function test_next_number_starts_with_orc(): void
    {
        $number = Quote::nextNumber($this->tenant->id);
        $this->assertStringStartsWith('ORC-', $number);
    }

    public function test_next_number_increments(): void
    {
        $n1 = Quote::nextNumber($this->tenant->id);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => $n1,
        ]);
        $n2 = Quote::nextNumber($this->tenant->id);
        $this->assertNotEquals($n1, $n2);
    }

    // ── Approval Token ──

    public function test_approval_token_is_deterministic(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $t1 = $q->approval_token;
        $t2 = $q->approval_token;
        $this->assertEquals($t1, $t2);
    }

    public function test_verify_approval_token_valid(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $token = $q->approval_token;
        $this->assertTrue(Quote::verifyApprovalToken($q->id, $token));
    }

    public function test_verify_approval_token_invalid(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertFalse(Quote::verifyApprovalToken($q->id, 'fake-token'));
    }

    public function test_public_access_token_prefers_magic_token_when_available(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'magic_token' => 'token-publico-canonico',
        ]);

        $this->assertSame('token-publico-canonico', $q->public_access_token);
    }

    // ── Approval URL ──

    public function test_approval_url_empty_without_magic_token(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'magic_token' => null,
        ]);
        $this->assertEquals('', $q->approval_url);
    }

    public function test_approval_url_contains_magic_token(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'magic_token' => 'abc123xyz',
        ]);
        $this->assertStringContainsString('abc123xyz', $q->approval_url);
        $this->assertStringContainsString('/quotes/proposal/', $q->approval_url);
    }

    public function test_approval_url_falls_back_to_app_url_when_frontend_url_is_localhost(): void
    {
        config()->set('app.frontend_url', 'http://localhost:3000');
        config()->set('app.url', 'https://app.example.test');

        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'magic_token' => 'public-token-123',
        ]);

        $this->assertSame(
            'https://app.example.test/quotes/proposal/public-token-123',
            $q->approval_url
        );
    }

    public function test_approval_url_prefers_public_frontend_url_when_configured(): void
    {
        config()->set('app.frontend_url', 'http://localhost:3000');
        config()->set('app.url', 'http://localhost');
        config()->set('app.public_frontend_url', 'https://app.example.test');

        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'magic_token' => 'public-token-configured',
        ]);

        $this->assertSame(
            'https://app.example.test/quotes/proposal/public-token-configured',
            $q->approval_url
        );
    }

    // ── PDF URL ──

    public function test_pdf_url_contains_token(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $url = $q->pdf_url;
        $this->assertStringContainsString("/api/v1/quotes/{$q->id}/public-pdf", $url);
        $this->assertStringContainsString('token=', $url);
    }

    public function test_pdf_url_prefers_public_app_url_when_configured(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('app.public_app_url', 'https://api.example.test');

        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertStringStartsWith('https://api.example.test/api/v1/quotes/', $q->pdf_url);
    }

    public function test_pdf_url_uses_public_access_token_when_magic_token_exists(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'magic_token' => 'token-pdf-publico',
        ]);

        $this->assertStringContainsString('token=token-pdf-publico', $q->pdf_url);
    }

    // ── Constants ──

    public function test_sources_constant(): void
    {
        $this->assertArrayHasKey('prospeccao', Quote::SOURCES);
        $this->assertArrayHasKey('retorno', Quote::SOURCES);
        $this->assertArrayHasKey('contato_direto', Quote::SOURCES);
        $this->assertArrayHasKey('indicacao', Quote::SOURCES);
    }

    public function test_statuses_constant(): void
    {
        $this->assertArrayHasKey('draft', Quote::STATUSES);
        $this->assertArrayHasKey('approved', Quote::STATUSES);
        $this->assertArrayHasKey('rejected', Quote::STATUSES);
        $this->assertArrayHasKey('invoiced', Quote::STATUSES);
    }

    // ── Casts ──

    public function test_status_cast_to_enum(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
        ]);
        $q->refresh();
        $this->assertInstanceOf(QuoteStatus::class, $q->status);
    }

    public function test_total_cast_to_decimal(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '5678.90',
        ]);
        $this->assertEquals('5678.90', $q->total);
    }

    public function test_is_template_cast_to_boolean(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_template' => true,
        ]);
        $this->assertTrue($q->is_template);
    }

    public function test_custom_fields_cast_to_array(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'custom_fields' => ['field1' => 'value1'],
        ]);
        $q->refresh();
        $this->assertIsArray($q->custom_fields);
    }

    // ── centralSyncData() ──

    public function test_central_sync_data_draft(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
            'quote_number' => 'ORC-00001',
        ]);
        $data = $q->centralSyncData();
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertStringContainsString('ORC-00001', $data['title']);
    }

    // ── Relationships ──

    public function test_quote_belongs_to_customer(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $q->customer);
    }

    public function test_quote_soft_deletes(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $q->delete();
        $this->assertSoftDeleted($q);
    }

    // ── profitMargin() ──

    public function test_profit_margin_zero_total(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
        ]);
        $this->assertEquals('0.0', $q->profitMargin());
    }
}
