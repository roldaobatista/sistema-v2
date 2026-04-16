<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Schedule;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TecnicosAgendaDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $tenantB;

    private User $user;

    private User $technicianA;

    private User $technicianB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->technicianA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->technicianB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // =========================================================
    //  AUTENTICAÇÃO — 401
    // =========================================================

    public function test_unauthenticated_schedule_index_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/schedules')->assertUnauthorized();
    }

    public function test_unauthenticated_schedule_store_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->postJson('/api/v1/schedules', [])->assertUnauthorized();
    }

    public function test_unauthenticated_technician_cash_index_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/technician-cash')->assertUnauthorized();
    }

    // =========================================================
    //  SCHEDULE — ISOLAMENTO TENANT
    // =========================================================

    public function test_index_only_returns_current_tenant_schedules(): void
    {
        Schedule::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
        ]);
        Schedule::factory(2)->create([
            'tenant_id' => $this->tenantB->id,
            'technician_id' => $this->technicianB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_show_returns_404_for_other_tenant_schedule(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'technician_id' => $this->technicianB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/v1/schedules/{$schedule->id}")->assertNotFound();
    }

    public function test_destroy_cannot_delete_other_tenant_schedule(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'technician_id' => $this->technicianB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/schedules/{$schedule->id}")->assertNotFound();
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'deleted_at' => null]);
    }

    // =========================================================
    //  SCHEDULE — VALIDAÇÃO
    // =========================================================

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/schedules', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['technician_id', 'title', 'scheduled_start', 'scheduled_end']);
    }

    public function test_store_rejects_end_before_start(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/schedules', [
            'technician_id' => $this->technicianA->id,
            'title' => 'Visita técnica',
            'scheduled_start' => now()->addDay()->toDateTimeString(),
            'scheduled_end' => now()->addDay()->subHour()->toDateTimeString(), // antes do start
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_end']);
    }

    public function test_store_rejects_technician_from_other_tenant(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/schedules', [
            'technician_id' => $this->technicianB->id,
            'title' => 'Visita técnica',
            'scheduled_start' => now()->addDay()->toDateTimeString(),
            'scheduled_end' => now()->addDay()->addHour()->toDateTimeString(),
        ]);

        // Retorna 422 (ensureTenantUser) ou 409 se tecnicamente conflito
        $this->assertContains($response->status(), [422, 409]);
    }

    // =========================================================
    //  SCHEDULE — HAPPY PATH
    // =========================================================

    public function test_store_creates_schedule_successfully(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $start = now()->addDays(2)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(2)->setTime(10, 0)->toDateTimeString();

        $response = $this->postJson('/api/v1/schedules', [
            'technician_id' => $this->technicianA->id,
            'title' => 'Manutenção preventiva',
            'notes' => 'Levar kit completo',
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'title', 'status', 'technician', 'scheduled_start', 'scheduled_end']]);

        $this->assertDatabaseHas('schedules', [
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'title' => 'Manutenção preventiva',
            'status' => Schedule::STATUS_SCHEDULED,
        ]);
    }

    public function test_store_creates_with_customer_and_returns_loaded_relations(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/schedules', [
            'technician_id' => $this->technicianA->id,
            'customer_id' => $customer->id,
            'title' => 'Visita agendada',
            'scheduled_start' => now()->addDays(5)->setTime(14, 0)->toDateTimeString(),
            'scheduled_end' => now()->addDays(5)->setTime(15, 30)->toDateTimeString(),
        ])
            ->assertCreated();

        $this->assertEquals($customer->id, $response->json('data.customer.id'));
        $this->assertEquals($this->technicianA->id, $response->json('data.technician.id'));
    }

    public function test_store_detects_schedule_conflict_returns_409(): void
    {
        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        // Agendamento já existente
        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // Tenta criar outro no mesmo horário
        $this->postJson('/api/v1/schedules', [
            'technician_id' => $this->technicianA->id,
            'title' => 'Conflito',
            'scheduled_start' => now()->addDays(3)->setTime(9, 30)->toDateTimeString(), // dentro do anterior
            'scheduled_end' => now()->addDays(3)->setTime(10, 30)->toDateTimeString(),
        ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Conflito de horario: tecnico já possui agendamento neste periodo.']);
    }

    public function test_cancelled_schedule_does_not_cause_conflict(): void
    {
        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        Schedule::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // Deve criar sem conflito — cancelado não bloqueia
        $this->postJson('/api/v1/schedules', [
            'technician_id' => $this->technicianA->id,
            'title' => 'Novo agendamento no horário cancelado',
            'scheduled_start' => now()->addDays(3)->setTime(9, 30)->toDateTimeString(),
            'scheduled_end' => now()->addDays(3)->setTime(10, 30)->toDateTimeString(),
        ])->assertCreated();
    }

    public function test_show_returns_schedule_with_relations(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/v1/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $schedule->id)
            ->assertJsonStructure(['data' => ['technician' => ['id', 'name'], 'customer' => ['id', 'name']]]);
    }

    public function test_update_changes_title_and_status(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("/api/v1/schedules/{$schedule->id}", [
            'title' => 'Título Atualizado',
            'status' => Schedule::STATUS_CONFIRMED,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Título Atualizado')
            ->assertJsonPath('data.status', Schedule::STATUS_CONFIRMED);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Título Atualizado',
            'status' => Schedule::STATUS_CONFIRMED,
        ]);
    }

    public function test_update_conflict_excludes_self(): void
    {
        $start = now()->addDays(4)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(4)->setTime(11, 0)->toDateTimeString();

        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // Atualizar o mesmo schedule no mesmo horário — não deve dar conflito consigo mesmo
        $this->putJson("/api/v1/schedules/{$schedule->id}", [
            'title' => 'Atualizado sem conflito',
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ])->assertOk();
    }

    public function test_destroy_soft_deletes_schedule(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/schedules/{$schedule->id}")->assertNoContent();

        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
    }

    // =========================================================
    //  SCHEDULE — UNIFIED
    // =========================================================

    public function test_unified_returns_schedules_in_period(): void
    {
        $from = now()->toDateString();
        $to = now()->addWeek()->toDateString();

        Schedule::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'scheduled_start' => now()->addDay()->setTime(9, 0),
            'scheduled_end' => now()->addDay()->setTime(10, 0),
        ]);

        // Fora do período
        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
            'scheduled_start' => now()->addMonths(2)->setTime(9, 0),
            'scheduled_end' => now()->addMonths(2)->setTime(10, 0),
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("/api/v1/schedules-unified?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['schedules_count', 'from', 'to']]);

        $this->assertGreaterThanOrEqual(2, $response->json('meta.schedules_count'));
    }

    public function test_index_filters_by_technician_id(): void
    {
        $otherTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        Schedule::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technicianA->id,
        ]);
        Schedule::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $otherTech->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/v1/schedules?technician_id={$this->technicianA->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // =========================================================
    //  TECHNICIAN CASH — ISOLAMENTO TENANT
    // =========================================================

    public function test_cash_index_only_returns_current_tenant_funds(): void
    {
        TechnicianCashFund::factory()->create(['tenant_id' => $this->tenant->id]);
        TechnicianCashFund::factory()->create(['tenant_id' => $this->tenant->id]);
        TechnicianCashFund::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/technician-cash')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // =========================================================
    //  TECHNICIAN CASH — CRÉDITO
    // =========================================================

    public function test_add_credit_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/technician-cash/credit', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'amount', 'description']);
    }

    public function test_add_credit_rejects_cross_tenant_user(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technicianB->id,
            'amount' => 500.00,
            'description' => 'Verba de viagem',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_add_credit_creates_transaction_and_updates_balance(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technicianA->id,
            'amount' => 300.00,
            'description' => 'Adiantamento mensal',
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->assertDatabaseHas('technician_cash_transactions', [
            'tenant_id' => $this->tenant->id,
            'type' => TechnicianCashTransaction::TYPE_CREDIT,
            'amount' => 300.00,
            'description' => 'Adiantamento mensal',
        ]);

        $fund = TechnicianCashFund::where('user_id', $this->technicianA->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();
        $this->assertEquals(300.00, (float) $fund->balance);
    }

    public function test_add_credit_via_corporate_card_updates_card_balance(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technicianA->id,
            'amount' => 200.00,
            'description' => 'Cartão corporativo',
            'payment_method' => 'corporate_card',
        ])->assertCreated();

        $fund = TechnicianCashFund::where('user_id', $this->technicianA->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertEquals(200.00, (float) $fund->card_balance);
        $this->assertEquals(0.00, (float) $fund->balance); // cash não mudou
    }

    // =========================================================
    //  TECHNICIAN CASH — DÉBITO
    // =========================================================

    public function test_add_debit_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/technician-cash/debit', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'amount', 'description']);
    }

    public function test_add_debit_rejects_insufficient_funds(): void
    {
        // Fundo com saldo zero
        TechnicianCashFund::getOrCreate($this->technicianA->id, $this->tenant->id);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/technician-cash/debit', [
            'user_id' => $this->technicianA->id,
            'amount' => 500.00,
            'description' => 'Despesa',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_add_debit_decrements_balance_after_credit(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        // Primeiro credita
        $this->postJson('/api/v1/technician-cash/credit', [
            'user_id' => $this->technicianA->id,
            'amount' => 500.00,
            'description' => 'Crédito inicial',
        ])->assertCreated();

        // Depois debita
        $this->postJson('/api/v1/technician-cash/debit', [
            'user_id' => $this->technicianA->id,
            'amount' => 150.00,
            'description' => 'Material consumido',
        ])->assertCreated();

        $fund = TechnicianCashFund::where('user_id', $this->technicianA->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertEquals(350.00, round((float) $fund->balance, 2));
    }

    public function test_add_debit_rejects_cross_tenant_user(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/technician-cash/debit', [
            'user_id' => $this->technicianB->id,
            'amount' => 50.00,
            'description' => 'Tentativa cross-tenant',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    // =========================================================
    //  TECHNICIAN CASH — SUMMARY
    // =========================================================

    public function test_summary_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/technician-cash-summary')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'total_balance',
                'total_card_balance',
                'month_credits',
                'month_debits',
                'funds_count',
            ]]);
    }

    public function test_summary_only_counts_current_tenant_funds(): void
    {
        TechnicianCashFund::factory()->create(['tenant_id' => $this->tenant->id, 'balance' => 100, 'card_balance' => 50]);
        TechnicianCashFund::factory()->create(['tenant_id' => $this->tenant->id, 'balance' => 200, 'card_balance' => 0]);
        TechnicianCashFund::factory()->create(['tenant_id' => $this->tenantB->id, 'balance' => 9999, 'card_balance' => 9999]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/technician-cash-summary')->assertOk();

        $this->assertEquals(2, $response->json('data.funds_count'));
        $this->assertEquals(300.00, (float) $response->json('data.total_balance'));
        $this->assertEquals(50.00, (float) $response->json('data.total_card_balance'));
    }

    // =========================================================
    //  MY FUND (MOBILE — técnico autenticado)
    // =========================================================

    public function test_my_fund_returns_authenticated_users_fund(): void
    {
        Sanctum::actingAs($this->technicianA, ['*']);

        $response = $this->getJson('/api/v1/technician-cash/my-fund')->assertOk();

        $this->assertEquals($this->technicianA->id, $response->json('data.user_id'));
    }

    public function test_my_fund_creates_fund_if_not_exists(): void
    {
        Sanctum::actingAs($this->technicianA, ['*']);

        $this->assertDatabaseMissing('technician_cash_funds', [
            'user_id' => $this->technicianA->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->getJson('/api/v1/technician-cash/my-fund')->assertOk();

        $this->assertDatabaseHas('technician_cash_funds', [
            'user_id' => $this->technicianA->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
        ]);
    }

    // =========================================================
    //  REQUEST FUNDS (MOBILE)
    // =========================================================

    public function test_request_funds_creates_pending_request(): void
    {
        Sanctum::actingAs($this->technicianA, ['*']);

        $this->postJson('/api/v1/technician-cash/request-funds', [
            'amount' => 250.00,
            'reason' => 'Preciso de verba para peças',
        ])->assertCreated();

        $this->assertDatabaseHas('technician_fund_requests', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->technicianA->id,
            'amount' => 250.00,
            'status' => 'pending',
        ]);
    }

    public function test_request_funds_validates_required_amount(): void
    {
        Sanctum::actingAs($this->technicianA, ['*']);

        $this->postJson('/api/v1/technician-cash/request-funds', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_request_funds_rejects_zero_amount(): void
    {
        Sanctum::actingAs($this->technicianA, ['*']);

        $this->postJson('/api/v1/technician-cash/request-funds', [
            'amount' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // =========================================================
    //  CASH — SHOW (extrato do técnico)
    // =========================================================

    public function test_show_cash_rejects_cross_tenant_user(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        // technicianB é de outro tenant
        $this->getJson("/api/v1/technician-cash/{$this->technicianB->id}")
            ->assertNotFound();
    }

    public function test_show_cash_returns_fund_and_paginated_transactions(): void
    {
        // Cria o fundo e uma transação
        $fund = TechnicianCashFund::getOrCreate($this->technicianA->id, $this->tenant->id);
        TechnicianCashTransaction::factory()->create([
            'fund_id' => $fund->id,
            'tenant_id' => $this->tenant->id,
            'type' => TechnicianCashTransaction::TYPE_CREDIT,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/v1/technician-cash/{$this->technicianA->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['fund', 'transactions' => ['data']]]);
    }
}
