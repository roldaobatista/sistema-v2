<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
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

    private function createReceivableAndPayment(array $paymentOverrides = [], array $receivableOverrides = []): Payment
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
        ], $receivableOverrides));

        // Disable model events to avoid recalculating status during test setup
        return Payment::withoutEvents(function () use ($receivable, $paymentOverrides) {
            return Payment::create(array_merge([
                'tenant_id' => $this->tenant->id,
                'payable_type' => AccountReceivable::class,
                'payable_id' => $receivable->id,
                'received_by' => $this->user->id,
                'amount' => 500.00,
                'payment_method' => 'pix',
                'payment_date' => now()->format('Y-m-d'),
                'notes' => null,
            ], $paymentOverrides));
        });
    }

    // ── INDEX ──────────────────────────────────────────────────

    public function test_index_returns_paginated_payments(): void
    {
        $this->createReceivableAndPayment();
        $this->createReceivableAndPayment();
        $this->createReceivableAndPayment();

        $response = $this->getJson('/api/v1/payment-receipts');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_filters_by_date_from(): void
    {
        $this->createReceivableAndPayment(['payment_date' => '2026-01-15']);
        $this->createReceivableAndPayment(['payment_date' => '2026-03-05']);

        $response = $this->getJson('/api/v1/payment-receipts?from=2026-03-01');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_date_to(): void
    {
        $this->createReceivableAndPayment(['payment_date' => '2026-01-15']);
        $this->createReceivableAndPayment(['payment_date' => '2026-03-05']);

        $response = $this->getJson('/api/v1/payment-receipts?to=2026-02-01');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->createReceivableAndPayment(['payment_date' => '2026-01-10']);
        $this->createReceivableAndPayment(['payment_date' => '2026-02-15']);
        $this->createReceivableAndPayment(['payment_date' => '2026-03-20']);

        $response = $this->getJson('/api/v1/payment-receipts?from=2026-02-01&to=2026-02-28');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_ordered_by_payment_date_desc(): void
    {
        $this->createReceivableAndPayment(['payment_date' => '2026-01-10', 'amount' => 100]);
        $this->createReceivableAndPayment(['payment_date' => '2026-03-20', 'amount' => 300]);
        $this->createReceivableAndPayment(['payment_date' => '2026-02-15', 'amount' => 200]);

        $response = $this->getJson('/api/v1/payment-receipts');

        $response->assertOk();
        $data = $response->json('data');
        $amounts = array_column($data, 'amount');
        // Latest first: 300, 200, 100
        $this->assertEquals('300.00', $amounts[0]);
        $this->assertEquals('200.00', $amounts[1]);
        $this->assertEquals('100.00', $amounts[2]);
    }

    // ── SHOW ──────────────────────────────────────────────────

    public function test_show_returns_payment_details(): void
    {
        $payment = $this->createReceivableAndPayment([
            'amount' => 750.00,
            'payment_method' => 'boleto',
            'notes' => 'Pagamento ref OS 123',
        ]);

        $response = $this->getJson("/api/v1/payment-receipts/{$payment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.amount', '750.00')
            ->assertJsonPath('data.payment_method', 'boleto')
            ->assertJsonPath('data.notes', 'Pagamento ref OS 123')
            ->assertJsonStructure([
                'data' => ['id', 'tenant_id', 'amount', 'payment_method', 'payment_date', 'received_by'],
            ]);
    }

    public function test_show_blocks_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 0,
        ]);

        $payment = Payment::withoutEvents(function () use ($otherTenant, $receivable) {
            return Payment::create([
                'tenant_id' => $otherTenant->id,
                'payable_type' => AccountReceivable::class,
                'payable_id' => $receivable->id,
                'received_by' => $this->user->id,
                'amount' => 250.00,
                'payment_method' => 'pix',
                'payment_date' => now()->format('Y-m-d'),
            ]);
        });

        $response = $this->getJson("/api/v1/payment-receipts/{$payment->id}");

        // BelongsToTenant global scope may cause 404 (model not found) instead of 403
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    // ── DOWNLOAD PDF ──────────────────────────────────────────

    public function test_download_pdf_blocks_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 0,
        ]);

        $payment = Payment::withoutEvents(function () use ($otherTenant, $receivable) {
            return Payment::create([
                'tenant_id' => $otherTenant->id,
                'payable_type' => AccountReceivable::class,
                'payable_id' => $receivable->id,
                'received_by' => $this->user->id,
                'amount' => 250.00,
                'payment_method' => 'pix',
                'payment_date' => now()->format('Y-m-d'),
            ]);
        });

        $response = $this->getJson("/api/v1/payment-receipts/{$payment->id}/pdf");

        // Model binding may return 404 (BelongsToTenant scope) or 403 (tenant check in controller)
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    // ── TENANT ISOLATION ──────────────────────────────────────

    public function test_index_only_shows_own_tenant_payments(): void
    {
        // Create payment for own tenant
        $this->createReceivableAndPayment(['amount' => 100.00]);

        // Create payment for another tenant
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'current_tenant_id' => $otherTenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'created_by' => $otherUser->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
        ]);
        Payment::withoutEvents(function () use ($otherTenant, $receivable, $otherUser) {
            Payment::create([
                'tenant_id' => $otherTenant->id,
                'payable_type' => AccountReceivable::class,
                'payable_id' => $receivable->id,
                'received_by' => $otherUser->id,
                'amount' => 500.00,
                'payment_method' => 'pix',
                'payment_date' => now()->format('Y-m-d'),
            ]);
        });

        $response = $this->getJson('/api/v1/payment-receipts');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── AUTH ──────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/payment-receipts');

        $response->assertStatus(401);
    }

    // ── PAGINATION ────────────────────────────────────────────

    public function test_index_respects_per_page_parameter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createReceivableAndPayment();
        }

        $response = $this->getJson('/api/v1/payment-receipts?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }
}
