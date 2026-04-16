<?php

namespace Tests\Unit\Models;

use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos do AccountPayable model real:
 * recalculateStatus() — core business logic, constants, casts, statuses().
 */
class AccountPayableRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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

        $this->actingAs($this->user);
    }

    // ── recalculateStatus() — the core business logic ──

    public function test_fully_paid_becomes_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'due_date' => now()->addDays(10),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PAID, $ap->status);
        $this->assertNotNull($ap->paid_at);
    }

    public function test_overpaid_becomes_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '500.00',
            'amount_paid' => '600.00',
            'due_date' => now()->addDays(10),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PAID, $ap->status);
    }

    public function test_partial_payment_becomes_partial(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '300.00',
            'due_date' => now()->addDays(10),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PARTIAL, $ap->status);
    }

    public function test_past_due_becomes_overdue(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(5),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::OVERDUE, $ap->status);
    }

    public function test_no_payment_future_due_stays_pending(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->addDays(30),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PENDING, $ap->status);
    }

    public function test_cancelled_is_not_recalculated(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '1000.00',
            'due_date' => now()->addDays(10),
            'status' => FinancialStatus::CANCELLED,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::CANCELLED, $ap->status);
    }

    public function test_renegotiated_is_not_recalculated(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(30),
            'status' => FinancialStatus::RENEGOTIATED,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::RENEGOTIATED, $ap->status);
    }

    public function test_past_due_with_partial_payment_stays_overdue(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '300.00',
            'due_date' => now()->subDays(5),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        // Past due date takes priority — even with partial payment
        $this->assertEquals(FinancialStatus::OVERDUE, $ap->status);
    }

    public function test_paid_at_is_set_when_fully_paid(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '100.00',
            'amount_paid' => '100.00',
            'due_date' => now()->addDays(10),
            'status' => FinancialStatus::PENDING,
            'paid_at' => null,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertNotNull($ap->paid_at);
    }

    public function test_paid_at_is_null_when_overdue(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1000.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(10),
            'status' => FinancialStatus::PENDING,
            'paid_at' => null,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertNull($ap->paid_at);
    }

    // ── statuses() method ──

    public function test_statuses_method_returns_from_enum(): void
    {
        $statuses = AccountPayable::statuses();
        $this->assertIsArray($statuses);
        $this->assertArrayHasKey('pending', $statuses);
        $this->assertArrayHasKey('paid', $statuses);
        $this->assertArrayHasKey('overdue', $statuses);
        $this->assertArrayHasKey('label', $statuses['pending']);
        $this->assertArrayHasKey('color', $statuses['pending']);
    }

    // ── Constants ──

    public function test_categories_constant(): void
    {
        $this->assertArrayHasKey('fornecedor', AccountPayable::CATEGORIES);
        $this->assertArrayHasKey('aluguel', AccountPayable::CATEGORIES);
        $this->assertArrayHasKey('salario', AccountPayable::CATEGORIES);
        $this->assertArrayHasKey('imposto', AccountPayable::CATEGORIES);
    }

    public function test_backward_compat_status_constants(): void
    {
        $this->assertEquals('pending', AccountPayable::STATUS_PENDING);
        $this->assertEquals('partial', AccountPayable::STATUS_PARTIAL);
        $this->assertEquals('paid', AccountPayable::STATUS_PAID);
        $this->assertEquals('overdue', AccountPayable::STATUS_OVERDUE);
        $this->assertEquals('cancelled', AccountPayable::STATUS_CANCELLED);
    }

    // ── Casts ──

    public function test_amount_cast_decimal(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '1234.56',
        ]);
        $this->assertEquals('1234.56', $ap->amount);
    }

    public function test_status_cast_to_enum(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => FinancialStatus::PENDING,
        ]);
        $ap->refresh();
        $this->assertInstanceOf(FinancialStatus::class, $ap->status);
    }

    public function test_due_date_cast_to_date(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'due_date' => '2026-06-15',
        ]);
        $this->assertInstanceOf(Carbon::class, $ap->due_date);
    }

    // ── Soft Delete ──

    public function test_soft_deletes(): void
    {
        $ap = AccountPayable::factory()->create(['tenant_id' => $this->tenant->id]);
        $ap->delete();
        $this->assertSoftDeleted($ap);
    }

    // ── centralSyncData() ──

    public function test_central_sync_data_pending(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => FinancialStatus::PENDING,
        ]);
        $data = $ap->centralSyncData();
        $this->assertArrayHasKey('status', $data);
    }
}
