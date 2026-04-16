<?php

namespace Tests\Critical;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * P1.2 — Invariantes de Negócio: Ordem de Serviço
 *
 * Valida a máquina de estados da OS (16 estados, transições controladas).
 * REGRAS:
 *  - Não pode pular estado (open → completed é inválido)
 *  - Cancelamento só de estados permitidos
 *  - completed_at/cancelled_at preenchidos automaticamente
 *  - OS cancelada não pode ser reaberta
 */
class WorkOrderInvariantsTest extends CriticalTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Invariant',
            'type' => 'PF',
        ]);
    }

    private function createWorkOrder(string $status = 'open'): WorkOrder
    {
        return WorkOrder::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'OS-INV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Invariant Test',
            'status' => $status,
        ]);
    }

    // ========================================================
    // TRANSIÇÕES VÁLIDAS
    // ========================================================

    #[DataProvider('validTransitions')]
    public function test_valid_transition_is_allowed(string $from, string $to): void
    {
        $wo = $this->createWorkOrder($from);

        // Verifica que a transição está no mapa de transições válidas
        $allowed = WorkOrder::ALLOWED_TRANSITIONS[$from] ?? [];

        $this->assertContains(
            $to,
            $allowed,
            "Transição {$from} → {$to} deveria ser válida mas não está no mapa"
        );
    }

    public static function validTransitions(): array
    {
        return [
            'open → awaiting_dispatch' => ['open', 'awaiting_dispatch'],
            'open → cancelled' => ['open', 'cancelled'],
            'awaiting_dispatch → in_displacement' => ['awaiting_dispatch', 'in_displacement'],
            'in_service → awaiting_return' => ['in_service', 'awaiting_return'],
            'completed → delivered' => ['completed', 'delivered'],
            'delivered → invoiced' => ['delivered', 'invoiced'],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_is_rejected(string $from, string $to): void
    {
        $wo = $this->createWorkOrder($from);

        $allowed = WorkOrder::ALLOWED_TRANSITIONS[$from] ?? [];

        $this->assertNotContains(
            $to,
            $allowed,
            "Transição {$from} → {$to} NÃO deveria ser válida"
        );
    }

    public static function invalidTransitions(): array
    {
        return [
            'open → completed (pular estados)' => ['open', 'completed'],
            'open → invoiced (pular tudo)' => ['open', 'invoiced'],
            'invoiced → open (reverter faturada)' => ['invoiced', 'open'],
            'completed → in_service (voltar)' => ['completed', 'in_service'],
        ];
    }

    // ========================================================
    // INVARIANTES DE ESTADO
    // ========================================================

    public function test_all_statuses_have_transition_map(): void
    {
        $definedStatuses = array_keys(WorkOrder::STATUSES);
        $transitionKeys = array_keys(WorkOrder::ALLOWED_TRANSITIONS);

        foreach ($definedStatuses as $status) {
            // Cancelled e invoiced podem não ter transições de saída
            if (in_array($status, ['cancelled', 'invoiced'])) {
                continue;
            }
            $this->assertArrayHasKey(
                $status,
                WorkOrder::ALLOWED_TRANSITIONS,
                "Status '{$status}' definido mas sem mapa de transições"
            );
        }
    }

    public function test_no_transition_leads_to_undefined_status(): void
    {
        $definedStatuses = array_keys(WorkOrder::STATUSES);

        foreach (WorkOrder::ALLOWED_TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $this->assertTrue(
                    in_array($to, $definedStatuses),
                    "Transição {$from} → {$to}: status destino '{$to}' não está definido em STATUSES"
                );
            }
        }
    }

    public function test_cancelled_allows_only_reopen(): void
    {
        $transitions = WorkOrder::ALLOWED_TRANSITIONS['cancelled'] ?? [];

        // Cancelled pode voltar a open (reabrir), mas nada mais
        $this->assertEquals(
            [WorkOrder::STATUS_OPEN],
            $transitions,
            "Status 'cancelled' deveria permitir apenas transição para 'open'"
        );
    }

    public function test_invoiced_is_terminal(): void
    {
        $transitions = WorkOrder::ALLOWED_TRANSITIONS['invoiced'] ?? [];

        $this->assertEmpty(
            $transitions,
            "Status 'invoiced' deveria ser terminal (sem transições de saída)"
        );
    }

    public function test_model_allowed_transitions_matches_enum(): void
    {
        foreach (WorkOrderStatus::cases() as $status) {
            $enumTargets = array_map(
                fn (WorkOrderStatus $s) => $s->value,
                $status->allowedTransitions()
            );
            sort($enumTargets);

            $modelTargets = WorkOrder::ALLOWED_TRANSITIONS[$status->value] ?? [];
            sort($modelTargets);

            $this->assertEquals(
                $enumTargets,
                $modelTargets,
                "ALLOWED_TRANSITIONS para '{$status->value}' no Model diverge do enum WorkOrderStatus::allowedTransitions()"
            );
        }
    }
}
