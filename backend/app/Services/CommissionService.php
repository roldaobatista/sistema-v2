<?php

namespace App\Services;

use App\Enums\CommissionEventStatus;
use App\Enums\ExpenseStatus;
use App\Events\CommissionGenerated;
use App\Models\AccountReceivable;
use App\Models\CommissionCampaign;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Gera comissões para uma OS (calcula, aplica campanhas, salva eventos).
     *
     * @param  string|null  $trigger  Quando a comissão foi acionada (os_completed, os_invoiced, installment_paid)
     *                                Retorna array de CommissionEvent criados.
     */
    public function calculateAndGenerate(WorkOrder $wo, ?string $trigger = null): array
    {
        $trigger = $trigger ?? CommissionRule::WHEN_OS_COMPLETED;

        // 1b. Não gera comissão para OS de garantia ou valor zero
        if ($wo->is_warranty || bccomp(Decimal::string($wo->total), '0', 2) <= 0) {
            return [];
        }

        // 2. Carregar dependências
        $wo->loadMissing(['items', 'technicians', 'customer']);

        // 3. Preparar contexto de cálculo (valores base) — bcmath
        $context = $this->buildCalculationContext($wo);

        // 4. Identificar beneficiários (quem deve receber)
        $beneficiaries = $this->identifyBeneficiaries($wo);

        // 5. Carregar campanhas ativas
        $campaigns = $this->loadActiveCampaigns($wo->tenant_id);

        $events = [];

        // 6. Processar regras para cada beneficiário (verificação de duplicatas DENTRO da transação)
        DB::transaction(function () use ($wo, $beneficiaries, $campaigns, $context, $trigger, &$events) {
            // 1. Verificar duplicatas com lock para evitar race condition
            $existing = CommissionEvent::where('tenant_id', $wo->tenant_id)
                ->where('work_order_id', $wo->id)
                ->where('notes', 'LIKE', "%trigger:{$trigger}%")
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                abort(422, 'Comissões já geradas para esta OS.');
            }
            foreach ($beneficiaries as $b) {
                $rules = CommissionRule::where('tenant_id', $wo->tenant_id)
                    ->where(function ($q) use ($b) {
                        $q->whereNull('user_id')->orWhere('user_id', $b['id']);
                    })
                    ->whereIn('applies_to_role', CommissionRule::aliasesForRole($b['role']))
                    ->where('active', true)
                    ->orderByDesc('priority')
                    ->get();

                // GAP-22: Determine commercial source for seller filter
                $quoteSource = null;
                if ($b['role'] === CommissionRule::ROLE_SELLER && $wo->quote_id) {
                    $quoteSource = $wo->quote?->source;
                }

                foreach ($rules as $rule) {
                    // GAP-22: If rule has a source filter, check match
                    if ($b['role'] === CommissionRule::ROLE_SELLER && $rule->source_filter && $quoteSource) {
                        if ($rule->source_filter !== $quoteSource) {
                            continue;
                        }
                    }

                    // Check applies_when: only generate if trigger matches
                    $ruleWhen = $rule->applies_when ?? CommissionRule::WHEN_OS_COMPLETED;
                    if ($ruleWhen !== $trigger) {
                        continue;
                    }

                    $commissionAmount = $rule->calculateCommission($wo->total, $context);

                    if (bccomp(Decimal::string($commissionAmount), '0', 2) <= 0) {
                        continue;
                    }

                    // GAP-05: Apply tech split divisor
                    $splitDivisor = $b['split_divisor'] ?? 1;
                    if ($splitDivisor > 1) {
                        $commissionAmount = bcdiv(Decimal::string($commissionAmount), Decimal::string($splitDivisor), 2);
                    }

                    $campaignResult = $this->applyCampaignMultiplier($campaigns, $b['role'], $rule->calculation_type, (string) $commissionAmount);

                    $notes = "Regra: {$rule->name} ({$rule->calculation_type}) | trigger:{$trigger}";
                    if ($splitDivisor > 1) {
                        $notes .= " | Divisão 1/{$splitDivisor}";
                    }
                    if ($campaignResult['campaign_name']) {
                        $notes .= " | Campanha: {$campaignResult['campaign_name']} (x{$campaignResult['multiplier']})";
                    }

                    $event = CommissionEvent::create([
                        'tenant_id' => $wo->tenant_id,
                        'commission_rule_id' => $rule->id,
                        'work_order_id' => $wo->id,
                        'user_id' => $b['id'],
                        'base_amount' => $wo->total,
                        'commission_amount' => $campaignResult['final_amount'],
                        'proportion' => 1.0000,
                        'status' => CommissionEventStatus::PENDING,
                        'notes' => $notes,
                    ]);

                    $events[] = $event;
                    break; // primeira regra aplicável por beneficiário (maior prioridade)
                }
            }
        });

        // Dispatch events after transaction commits — safe because data is already persisted
        if (! empty($events)) {
            try {
                foreach ($events as $event) {
                    CommissionGenerated::dispatch($event);
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao notificar comissões geradas', [
                    'work_order_id' => $wo->id,
                    'events_count' => count($events),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $events;
    }

    /**
     * Gera comissões para uma OS testando todos os triggers possíveis.
     * Usado para geração retroativa em lote onde o trigger original é desconhecido.
     */
    public function calculateAndGenerateAnyTrigger(WorkOrder $wo): array
    {
        $triggers = [
            CommissionRule::WHEN_OS_COMPLETED,
            CommissionRule::WHEN_OS_INVOICED,
            CommissionRule::WHEN_INSTALLMENT_PAID,
        ];

        foreach ($triggers as $trigger) {
            try {
                $events = $this->calculateAndGenerate($wo, $trigger);
                if (count($events) > 0) {
                    return $events;
                }
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'já geradas')) {
                    throw $e;
                }
                Log::debug('CommissionService: trigger skipped', [
                    'work_order_id' => $wo->id,
                    'trigger' => $trigger,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    /**
     * Simula comissões para UI (não salva nada). Aplica campanhas ativas.
     */
    public function simulate(WorkOrder $wo): array
    {
        $wo->loadMissing(['items', 'technicians', 'customer']);

        if ($wo->is_warranty || bccomp(Decimal::string($wo->total), '0', 2) <= 0) {
            return [];
        }

        $context = $this->buildCalculationContext($wo);
        $beneficiaries = $this->identifyBeneficiaries($wo);
        $campaigns = $this->loadActiveCampaigns($wo->tenant_id);
        $simulations = [];

        foreach ($beneficiaries as $b) {
            $rules = CommissionRule::where('tenant_id', $wo->tenant_id)
                ->where(function ($q) use ($b) {
                    $q->whereNull('user_id')->orWhere('user_id', $b['id']);
                })
                ->whereIn('applies_to_role', CommissionRule::aliasesForRole($b['role']))
                ->where('active', true)
                ->orderByDesc('priority')
                ->get();

            // GAP-22: Determine commercial source for seller filter
            $quoteSource = null;
            if ($b['role'] === CommissionRule::ROLE_SELLER && $wo->quote_id) {
                $quoteSource = $wo->quote?->source;
            }

            foreach ($rules as $rule) {
                // GAP-22: If rule has a source filter, check match
                if ($b['role'] === CommissionRule::ROLE_SELLER && $rule->source_filter && $quoteSource) {
                    if ($rule->source_filter !== $quoteSource) {
                        continue;
                    }
                }

                $amount = $rule->calculateCommission($wo->total, $context);

                if (bccomp(Decimal::string($amount), '0', 2) <= 0) {
                    continue;
                }

                // GAP-05: Apply tech split divisor
                $splitDivisor = $b['split_divisor'] ?? 1;
                if ($splitDivisor > 1) {
                    $amount = bcdiv(Decimal::string($amount), Decimal::string($splitDivisor), 2);
                }

                $campaignResult = $this->applyCampaignMultiplier($campaigns, $b['role'], $rule->calculation_type, (string) $amount);

                $userName = $rule->user?->name ?? User::find($b['id'])?->name ?? 'Usuário '.$b['id'];

                $notes = "Regra: {$rule->name} ({$rule->calculation_type})";
                if ($splitDivisor > 1) {
                    $notes .= " | Divisão 1/{$splitDivisor}";
                }
                if ($campaignResult['campaign_name']) {
                    $notes .= " | Campanha: {$campaignResult['campaign_name']} (x{$campaignResult['multiplier']})";
                }

                $simulations[] = [
                    'user_id' => $b['id'],
                    'user_name' => $userName,
                    'rule_name' => $rule->name,
                    'calculation_type' => $rule->calculation_type,
                    'applies_to_role' => $rule->applies_to_role,
                    'applies_when' => $rule->applies_when ?? CommissionRule::WHEN_OS_COMPLETED,
                    'base_amount' => bcadd(Decimal::string($wo->total), '0', 2),
                    'commission_amount' => bcadd($campaignResult['final_amount'], '0', 2),
                    'multiplier' => $campaignResult['multiplier'],
                    'campaign_name' => $campaignResult['campaign_name'],
                    'split_divisor' => $splitDivisor,
                    'notes' => $notes,
                ];

                break; // primeira regra aplicável por beneficiário (maior prioridade)
            }
        }

        return $simulations;
    }

    private function identifyBeneficiaries(WorkOrder $wo): array
    {
        $list = [];
        $techIds = [];

        // 1. Técnico Principal
        if ($wo->assigned_to) {
            $list[] = ['id' => $wo->assigned_to, 'role' => CommissionRule::ROLE_TECHNICIAN];
            $techIds[] = $wo->assigned_to;
        }

        // 4. Técnicos Auxiliares (N:N)
        foreach ($wo->technicians as $tech) {
            $role = CommissionRule::normalizeRole($tech->pivot->role ?? CommissionRule::ROLE_TECHNICIAN) ?? CommissionRule::ROLE_TECHNICIAN;
            $exists = collect($list)->contains(fn ($item) => $item['id'] == $tech->id && $item['role'] == $role);
            if (! $exists) {
                $list[] = ['id' => $tech->id, 'role' => $role];
                if ($role === CommissionRule::ROLE_TECHNICIAN) {
                    $techIds[] = $tech->id;
                }
            }
        }

        // GAP-05: Count technicians for 50% auto-split
        $techCount = count(array_unique($techIds));

        // Mark each tech entry with the split divisor
        $list = array_map(function ($item) use ($techCount) {
            if ($item['role'] === CommissionRule::ROLE_TECHNICIAN && $techCount > 1) {
                $item['split_divisor'] = $techCount;
            } else {
                $item['split_divisor'] = 1;
            }

            return $item;
        }, $list);

        // 2. Vendedor
        if ($wo->seller_id) {
            // GAP-07: Block same person from earning both tech + seller on same OS
            $isAlsoTech = in_array($wo->seller_id, $techIds);
            if (! $isAlsoTech) {
                $list[] = ['id' => $wo->seller_id, 'role' => CommissionRule::ROLE_SELLER, 'split_divisor' => 1];
            } else {
                Log::info('CommissionService: Seller #{id} is also a technician on OS #{os}. Seller commission blocked (GAP-07).', [
                    'id' => $wo->seller_id, 'os' => $wo->os_number,
                ]);
            }
        }

        // 3. Motorista
        if ($wo->driver_id) {
            $list[] = ['id' => $wo->driver_id, 'role' => CommissionRule::ROLE_DRIVER, 'split_divisor' => 1];
        }

        return $list;
    }

    /**
     * GAP-04: Libera comissões proporcionalmente quando um pagamento é recebido.
     * Se a OS tem total R$10.000 e o pagamento é R$5.000, libera 50% da comissão.
     */
    public function releaseByPayment(AccountReceivable $ar, ?Payment $payment = null): void
    {
        if (! $ar->work_order_id) {
            return;
        }

        DB::transaction(function () use ($ar, $payment) {
            $wo = WorkOrder::find($ar->work_order_id);
            if (! $wo || bccomp(Decimal::string($wo->total), '0', 2) <= 0) {
                return;
            }

            if ($payment) {
                $paymentMarker = "pgto #{$payment->id}";
                $alreadyReleased = CommissionEvent::where('tenant_id', $ar->tenant_id)
                    ->where('work_order_id', $ar->work_order_id)
                    ->where('account_receivable_id', $ar->id)
                    ->where('status', CommissionEventStatus::APPROVED)
                    ->where('notes', 'like', "%{$paymentMarker}%")
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyReleased) {
                    return;
                }
            }

            $paymentAmount = (string) ($payment?->amount ?? $ar->amount);
            if (bccomp($paymentAmount, '0', 2) <= 0) {
                return;
            }

            // Prevenir divisão por zero se total da OS for 0
            /** @phpstan-ignore smallerOrEqual.alwaysFalse */
            if (bccomp(Decimal::string($wo->total), '0', 2) <= 0) {
                return;
            }

            $proportion = bcdiv($paymentAmount, Decimal::string($wo->total), 4);
            if (bccomp($proportion, '1', 4) > 0) {
                $proportion = '1.0000';
            }

            $pendingEvents = CommissionEvent::where('work_order_id', $ar->work_order_id)
                ->where('tenant_id', $ar->tenant_id)
                ->where('status', CommissionEventStatus::PENDING)
                ->lockForUpdate()
                ->get();

            foreach ($pendingEvents as $event) {
                $remainingBaseAmount = Decimal::string($event->base_amount ?? $wo->total);
                if (bccomp($remainingBaseAmount, '0', 2) <= 0) {
                    continue;
                }

                $paymentShareOfRemaining = bcdiv($paymentAmount, $remainingBaseAmount, 6);
                if (bccomp($paymentShareOfRemaining, '1', 6) > 0) {
                    $paymentShareOfRemaining = '1.000000';
                }

                $proportionalAmount = bcmul(Decimal::string($event->commission_amount), $paymentShareOfRemaining, 2);

                if (bccomp($proportion, '1', 4) < 0) {
                    $remainingAmount = bcsub(Decimal::string($event->commission_amount), $proportionalAmount, 2);
                    $remainingBase = bcsub($remainingBaseAmount, $paymentAmount, 2);
                    if (bccomp($remainingBase, '0', 2) < 0) {
                        $remainingBase = '0.00';
                    }

                    CommissionEvent::create([
                        'tenant_id' => $event->tenant_id,
                        'commission_rule_id' => $event->commission_rule_id,
                        'work_order_id' => $event->work_order_id,
                        'account_receivable_id' => $ar->id,
                        'user_id' => $event->user_id,
                        'base_amount' => $paymentAmount,
                        'commission_amount' => $proportionalAmount,
                        'proportion' => $proportion,
                        'status' => CommissionEventStatus::APPROVED,
                        'notes' => ($event->notes ?? '')." | Liberada proporcional ({$proportion}) pgto #".($payment?->id ?? $ar->id),
                    ]);

                    $event->update([
                        'base_amount' => $remainingBase,
                        'commission_amount' => $remainingAmount,
                        'notes' => ($event->notes ?? '').' | Restante apos pgto parcial #'.($payment?->id ?? $ar->id),
                    ]);
                } else {
                    $event->update([
                        'status' => CommissionEventStatus::APPROVED,
                        'account_receivable_id' => $ar->id,
                        'base_amount' => $paymentAmount,
                        'proportion' => $proportion,
                        'notes' => ($event->notes ?? '').' | Liberada por pagamento #'.($payment?->id ?? $ar->id).' em '.now()->format('d/m/Y'),
                    ]);
                }
            }
        });
    }

    /**
     * Constrói contexto de cálculo com valores base da OS — usando bcmath.
     */
    public function reverseByPayment(AccountReceivable $ar, Payment $payment): void
    {
        if (! $ar->work_order_id) {
            return;
        }

        DB::transaction(function () use ($ar, $payment) {
            $paymentMarker = "pgto #{$payment->id}";

            $relatedEvents = CommissionEvent::where('tenant_id', $ar->tenant_id)
                ->where('work_order_id', $ar->work_order_id)
                ->where('account_receivable_id', $ar->id)
                ->whereIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
                ->where('notes', 'like', "%{$paymentMarker}%")
                ->lockForUpdate()
                ->get();

            if ($relatedEvents->isEmpty()) {
                return;
            }

            if ($relatedEvents->where('status', CommissionEventStatus::PAID)->isNotEmpty()) {
                abort(422, 'Nao e possivel estornar pagamento com comissao ja liquidada.');
            }

            foreach ($relatedEvents as $event) {
                $notes = (string) ($event->notes ?? '');
                $isPartialRelease = str_contains($notes, 'Liberada proporcional');

                if ($isPartialRelease) {
                    $pendingEvent = CommissionEvent::where('tenant_id', $event->tenant_id)
                        ->where('work_order_id', $event->work_order_id)
                        ->where('commission_rule_id', $event->commission_rule_id)
                        ->where('user_id', $event->user_id)
                        ->where('status', CommissionEventStatus::PENDING)
                        ->lockForUpdate()
                        ->first();

                    if ($pendingEvent) {
                        $pendingEvent->update([
                            'base_amount' => bcadd(Decimal::string($pendingEvent->base_amount), Decimal::string($event->base_amount), 2),
                            'commission_amount' => bcadd(Decimal::string($pendingEvent->commission_amount), Decimal::string($event->commission_amount), 2),
                            'notes' => ($pendingEvent->notes ?? '')." | Saldo restaurado pelo estorno pgto #{$payment->id}",
                        ]);
                    } else {
                        CommissionEvent::create([
                            'tenant_id' => $event->tenant_id,
                            'commission_rule_id' => $event->commission_rule_id,
                            'work_order_id' => $event->work_order_id,
                            'user_id' => $event->user_id,
                            'base_amount' => $event->base_amount,
                            'commission_amount' => $event->commission_amount,
                            'proportion' => $event->proportion,
                            'status' => CommissionEventStatus::PENDING,
                            'notes' => ($event->notes ?? '')." | Reaberta por estorno pgto #{$payment->id}",
                        ]);
                    }

                    $event->update([
                        'status' => CommissionEventStatus::REVERSED,
                        'notes' => $notes." | Estornada pelo pagamento #{$payment->id}",
                    ]);

                    continue;
                }

                $event->update([
                    'status' => CommissionEventStatus::PENDING,
                    'account_receivable_id' => null,
                    'notes' => $notes." | Reaberta por estorno pgto #{$payment->id}",
                ]);
            }
        });
    }

    private function buildCalculationContext(WorkOrder $wo): array
    {
        $expensesTotal = Expense::withoutGlobalScopes()
            ->where('tenant_id', $wo->tenant_id)
            ->where('work_order_id', $wo->id)
            ->where('status', ExpenseStatus::APPROVED)
            ->where(function ($q) {
                $q->whereIn('affects_net_value', [true, 1])
                    ->orWhereNull('affects_net_value');
            })
            ->sum('amount');

        $itemsCost = '0';
        foreach ($wo->items as $item) {
            $itemsCost = bcadd($itemsCost, bcmul(Decimal::string($item->cost_price), Decimal::string($item->quantity), 2), 2);
        }

        $productsTotal = '0';
        $servicesTotal = '0';
        foreach ($wo->items as $item) {
            if ($item->type === 'product') {
                $productsTotal = bcadd($productsTotal, Decimal::string($item->total), 2);
            } else {
                $servicesTotal = bcadd($servicesTotal, Decimal::string($item->total), 2);
            }
        }

        return [
            'gross' => Decimal::string($wo->total),
            'expenses' => Decimal::string($expensesTotal),
            'displacement' => Decimal::string($wo->displacement_value),
            'products_total' => $productsTotal,
            'services_total' => $servicesTotal,
            'cost' => $itemsCost,
            'items_count' => $wo->items->count(),
        ];
    }

    /**
     * Carrega campanhas ativas para o tenant.
     */
    private function loadActiveCampaigns(int $tenantId): Collection
    {
        return CommissionCampaign::where('tenant_id', $tenantId)
            ->active()
            ->get();
    }

    /**
     * Aplica o maior multiplicador de campanha aplicável — bcmath.
     */
    private function applyCampaignMultiplier(Collection $campaigns, string $role, string $calculationType, string $baseAmount): array
    {
        $multiplier = '1';
        $campaignName = null;

        foreach ($campaigns as $campaign) {
            if ($campaign->applies_to_role && CommissionRule::normalizeRole($campaign->applies_to_role) !== CommissionRule::normalizeRole($role)) {
                continue;
            }
            if (isset($campaign->applies_to_calculation_type) && $campaign->applies_to_calculation_type && $campaign->applies_to_calculation_type !== $calculationType) {
                continue;
            }
            if (bccomp(Decimal::string($campaign->multiplier), $multiplier, 2) > 0) {
                $multiplier = (string) $campaign->multiplier;
                $campaignName = $campaign->name;
            }
        }

        return [
            'final_amount' => bcmul($baseAmount, $multiplier, 2),
            'multiplier' => $multiplier,
            'campaign_name' => $campaignName,
        ];
    }
}
