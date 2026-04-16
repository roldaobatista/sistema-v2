<?php

namespace App\Console\Commands;

use App\Enums\QuoteStatus;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmMessage;
use App\Models\CrmMessageTemplate;
use App\Models\CrmPipeline;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Services\MessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCrmAutomations extends Command
{
    protected $signature = 'crm:process-automations {--tenant= : Processar apenas um tenant}';

    protected $description = 'Processa automações CRM: calibração→deal, OS→follow-up, 90d→alerta, orçamento→won, health<50, contrato→renovação';

    private int $dealsCreated = 0;

    private int $activitiesCreated = 0;

    private int $customersUpdated = 0;

    private int $messagesSent = 0;

    public function handle(): int
    {
        $tenantQuery = Tenant::query();
        if ($id = $this->option('tenant')) {
            $tenantQuery->where('id', $id);
        }

        $tenants = $tenantQuery->where('status', Tenant::STATUS_ACTIVE)->get();

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $this->info("── Tenant: {$tenant->name} (#{$tenant->id}) ──");

                $steps = [
                    'processCalibrationDeals',
                    'processCompletedWorkOrders',
                    'processNoContactAlert',
                    'processApprovedQuotes',
                    'processLowHealthScore',
                    'processExpiringContracts',
                    'processCalibrationMessages',
                    'processContractMessages',
                    'recalculateHealthScores',
                ];

                foreach ($steps as $step) {
                    try {
                        $this->$step($tenant);
                    } catch (\Throwable $e) {
                        Log::error("ProcessCrmAutomations: falha em {$step} no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                        $this->error("  {$step}: {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("ProcessCrmAutomations: falha total no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('✅ Resumo:');
        $this->info("   Deals criados: {$this->dealsCreated}");
        $this->info("   Atividades criadas: {$this->activitiesCreated}");
        $this->info("   Mensagens enviadas: {$this->messagesSent}");
        $this->info("   Clientes atualizados: {$this->customersUpdated}");

        return Command::SUCCESS;
    }

    /**
     * 1. Calibração vencendo → Criar deal no pipeline "Recalibração"
     */
    private function processCalibrationDeals(Tenant $tenant): void
    {
        $pipeline = CrmPipeline::where('tenant_id', $tenant->id)
            ->where('slug', 'recalibracao')
            ->first();

        if (! $pipeline) {
            return;
        }

        $firstStage = $pipeline->stages()->orderBy('sort_order')->first();
        if (! $firstStage) {
            return;
        }

        $equipments = Equipment::where('tenant_id', $tenant->id)
            ->calibrationDue(30)
            ->active()
            ->whereNotNull('customer_id')
            ->get();

        foreach ($equipments as $eq) {
            try {
                // Verificar se já existe deal aberto para este equipamento
                $exists = CrmDeal::where('tenant_id', $tenant->id)
                    ->where('equipment_id', $eq->id)
                    ->where('source', 'calibracao_vencendo')
                    ->open()
                    ->exists();

                if ($exists) {
                    continue;
                }

                $daysUntil = (int) now()->diffInDays($eq->next_calibration_at, false);
                $status = $daysUntil < 0 ? 'VENCIDA' : "vence em {$daysUntil}d";

                $deal = CrmDeal::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $eq->customer_id,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $firstStage->id,
                    'title' => "Calibração {$eq->code} ({$status})",
                    'value' => 0,
                    'probability' => $firstStage->probability,
                    'source' => 'calibracao_vencendo',
                    'equipment_id' => $eq->id,
                    'expected_close_date' => $eq->next_calibration_at,
                    'notes' => "Gerado automaticamente. Equipamento: {$eq->brand} {$eq->model} (S/N: {$eq->serial_number})",
                ]);

                CrmActivity::logSystemEvent(
                    $tenant->id,
                    $eq->customer_id,
                    "Deal criado: calibração de {$eq->code} ({$eq->brand} {$eq->model}) {$status}",
                    $deal->id
                );

                // Notificar vendedor do deal criado
                $deal->load('customer');
                $sellerId = $eq->customer?->assigned_seller_id;
                if ($sellerId) {
                    try {
                        Notification::crmDealCreated($deal, $sellerId);
                    } catch (\Throwable $e) {
                        Log::warning("ProcessCrmAutomations: notificação calibração falhou eq #{$eq->id}", ['error' => $e->getMessage()]);
                    }
                }

                $this->dealsCreated++;
                $this->activitiesCreated++;
            } catch (\Throwable $e) {
                Log::warning("ProcessCrmAutomations: falha em processCalibrationDeals eq #{$eq->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($this->dealsCreated > 0) {
            $this->line("  📐 {$this->dealsCreated} deal(s) de calibração criados");
        }
    }

    /**
     * 2. OS concluída → Follow-up na timeline do cliente
     */
    private function processCompletedWorkOrders(Tenant $tenant): void
    {
        $count = 0;

        // OS concluídas nos últimos 2 dias que ainda não têm follow-up automático
        $workOrders = WorkOrder::where('tenant_id', $tenant->id)
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->where('updated_at', '>=', now()->subDays(2))
            ->with('technicians:id')
            ->get();

        foreach ($workOrders as $wo) {
            try {
                $exists = CrmActivity::where('tenant_id', $tenant->id)
                    ->where('customer_id', $wo->customer_id)
                    ->where('type', 'system')
                    ->where('title', 'like', "%OS #{$wo->business_number}%follow-up%")
                    ->where('created_at', '>=', now()->subDays(3))
                    ->exists();

                if ($exists) {
                    continue;
                }

                CrmActivity::create([
                    'tenant_id' => $tenant->id,
                    'type' => 'tarefa',
                    'customer_id' => $wo->customer_id,
                    'user_id' => $wo->technicians->first()?->id ?? $wo->assigned_to ?? 1,
                    'title' => "Follow-up: OS #{$wo->business_number} concluída — verificar satisfação",
                    'description' => "A OS #{$wo->business_number} foi finalizada. Ligue ou envie mensagem para o cliente para verificar satisfação e identificar novas oportunidades.",
                    'scheduled_at' => now()->addDays(3),
                    'is_automated' => true,
                    'channel' => 'telefone',
                ]);

                // Atualizar last_contact_at do cliente
                Customer::where('id', $wo->customer_id)->update([
                    'next_follow_up_at' => now()->addDays(3),
                ]);

                $count++;
                $this->activitiesCreated++;
            } catch (\Throwable $e) {
                Log::warning("ProcessCrmAutomations: falha em processCompletedWorkOrders wo #{$wo->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($count > 0) {
            $this->line("  📋 {$count} follow-up(s) de OS criados");
        }
    }

    /**
     * 3. 90 dias sem contato → Alerta + atividade de recontato
     */
    private function processNoContactAlert(Tenant $tenant): void
    {
        $count = 0;

        $customers = Customer::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('last_contact_at', '<', now()->subDays(90))
                    ->orWhereNull('last_contact_at');
            })
            ->get();

        foreach ($customers as $customer) {
            try {
                // Já tem atividade de alerta recente?
                $exists = CrmActivity::where('tenant_id', $tenant->id)
                    ->where('customer_id', $customer->id)
                    ->where('type', 'system')
                    ->where('title', 'like', '%90 dias sem contato%')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->exists();

                if ($exists) {
                    continue;
                }

                $lastContact = $customer->last_contact_at
                    ? $customer->last_contact_at->diffForHumans()
                    : 'nunca';

                CrmActivity::create([
                    'tenant_id' => $tenant->id,
                    'type' => 'tarefa',
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_seller_id ?? 1,
                    'title' => "⚠️ 90 dias sem contato com {$customer->name}",
                    'description' => "Último contato: {$lastContact}. Faça contato para manter o relacionamento e identificar oportunidades.",
                    'scheduled_at' => now()->addDay(),
                    'is_automated' => true,
                ]);

                $count++;
                $this->activitiesCreated++;
            } catch (\Throwable $e) {
                Log::warning("ProcessCrmAutomations: falha em processNoContactAlert customer #{$customer->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($count > 0) {
            $this->line("  ⚠️  {$count} alerta(s) de 90d sem contato");
        }
    }

    /**
     * 4. Orçamento aprovado → Deal won (se vinculado a um deal aberto)
     */
    private function processApprovedQuotes(Tenant $tenant): void
    {
        $count = 0;

        $deals = CrmDeal::where('tenant_id', $tenant->id)
            ->open()
            ->whereNotNull('quote_id')
            ->get();

        foreach ($deals as $deal) {
            try {
                $quote = Quote::find($deal->quote_id);
                $quoteStatus = $quote?->status instanceof QuoteStatus
                    ? $quote->status->value
                    : $quote?->status;
                if (! $quote || ! in_array($quoteStatus, [Quote::STATUS_APPROVED, Quote::STATUS_INVOICED], true)) {
                    continue;
                }

                $deal->update(['value' => $quote->total]);
                $deal->markAsWon();
                $statusLabel = $quoteStatus === Quote::STATUS_INVOICED ? 'faturado' : 'aprovado';

                CrmActivity::logSystemEvent(
                    $tenant->id,
                    $deal->customer_id,
                    "Deal ganho automaticamente: orçamento #{$quote->quote_number} {$statusLabel} (R$ ".number_format((float) $quote->total, 2, ',', '.').')',
                    $deal->id
                );

                $count++;
                $this->activitiesCreated++;
            } catch (\Throwable $e) {
                Log::warning("ProcessCrmAutomations: falha em processApprovedQuotes deal #{$deal->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($count > 0) {
            $this->line("  {$count} deal(s) marcados como ganhos por orçamento aprovado/faturado");
        }
    }

    /**
     * 5. Health Score < 50 → Notificação para seller/admin
     */
    private function processLowHealthScore(Tenant $tenant): void
    {
        $count = 0;

        $customers = Customer::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('health_score', '<', 50)
            ->where('health_score', '>', 0)
            ->get();

        foreach ($customers as $customer) {
            try {
                // Já tem alerta recente?
                $exists = CrmActivity::where('tenant_id', $tenant->id)
                    ->where('customer_id', $customer->id)
                    ->where('type', 'system')
                    ->where('title', 'like', '%Health Score baixo%')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->exists();

                if ($exists) {
                    continue;
                }

                CrmActivity::logSystemEvent(
                    $tenant->id,
                    $customer->id,
                    "🔴 Health Score baixo: {$customer->name} está com score {$customer->health_score}/100",
                    null,
                    $customer->assigned_seller_id,
                    ['health_score' => $customer->health_score, 'breakdown' => $customer->health_score_breakdown]
                );

                // Notificar vendedor/gestor do health score crítico
                $sellerId = $customer->assigned_seller_id;
                if ($sellerId) {
                    try {
                        Notification::crmHealthAlert($customer, $sellerId);
                    } catch (\Throwable $e) {
                        Log::warning("ProcessCrmAutomations: notificação health alert falhou customer #{$customer->id}", ['error' => $e->getMessage()]);
                    }
                }

                $count++;
                $this->activitiesCreated++;
            } catch (\Throwable $e) {
                Log::warning("ProcessCrmAutomations: falha em processLowHealthScore customer #{$customer->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($count > 0) {
            $this->line("  🔴 {$count} alerta(s) de health score baixo");
        }
    }

    /**
     * 6. Contrato vencendo em 60 dias → Deal de renovação
     */
    private function processExpiringContracts(Tenant $tenant): void
    {
        $count = 0;

        $pipeline = CrmPipeline::where('tenant_id', $tenant->id)
            ->where('slug', 'contrato')
            ->first();

        if (! $pipeline) {
            return;
        }

        $firstStage = $pipeline->stages()->orderBy('sort_order')->first();
        if (! $firstStage) {
            return;
        }

        $customers = Customer::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('contract_end')
            ->where('contract_end', '<=', now()->addDays(60))
            ->where('contract_end', '>=', now())
            ->get();

        foreach ($customers as $customer) {
            try {
                $exists = CrmDeal::where('tenant_id', $tenant->id)
                    ->where('customer_id', $customer->id)
                    ->where('source', 'contrato_renovacao')
                    ->open()
                    ->exists();

                if ($exists) {
                    continue;
                }

                $daysUntil = (int) now()->diffInDays($customer->contract_end, false);

                $deal = CrmDeal::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $firstStage->id,
                    'title' => "Renovação contrato {$customer->name} (vence em {$daysUntil}d)",
                    'value' => bcadd((string) ($customer->annual_revenue_estimate ?? 0), '0', 2),
                    'probability' => $firstStage->probability,
                    'source' => 'contrato_renovacao',
                    'expected_close_date' => $customer->contract_end,
                    'notes' => "Contrato {$customer->contract_type} vence em {$customer->contract_end->format('d/m/Y')}. Iniciar negociação de renovação.",
                ]);

                CrmActivity::logSystemEvent(
                    $tenant->id,
                    $customer->id,
                    "Deal de renovação criado: contrato vence em {$daysUntil} dias",
                    $deal->id
                );

                // Notificar vendedor do deal de renovação
                $deal->load('customer');
                $sellerId = $customer->assigned_seller_id;
                if ($sellerId) {
                    try {
                        Notification::crmDealCreated($deal, $sellerId);
                    } catch (\Throwable $e) {
                        Log::warning("ProcessCrmAutomations: notificação renovação falhou customer #{$customer->id}", ['error' => $e->getMessage()]);
                    }
                }

                $count++;
                $this->dealsCreated++;
                $this->activitiesCreated++;
            } catch (\Throwable $e) {
                Log::warning("ProcessCrmAutomations: falha em processExpiringContracts customer #{$customer->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($count > 0) {
            $this->line("  📄 {$count} deal(s) de renovação de contrato");
        }
    }

    /**
     * 7. Calibração vencendo → Enviar WhatsApp de lembrete
     */
    private function processCalibrationMessages(Tenant $tenant): void
    {
        $count = 0;
        $service = app(MessagingService::class);

        $equipments = Equipment::where('tenant_id', $tenant->id)
            ->calibrationDue(15)
            ->active()
            ->whereNotNull('customer_id')
            ->with('customer:id,name,phone')
            ->get();

        // Tentar usar template
        $template = CrmMessageTemplate::where('tenant_id', $tenant->id)
            ->where('slug', 'lembrete-calibracao')
            ->where('channel', 'whatsapp')
            ->active()
            ->first();

        foreach ($equipments as $eq) {
            /** @var Customer|null $customer */
            $customer = $eq->customer;
            if (! $customer || ! $customer->phone) {
                continue;
            }

            // Já enviou lembrete nos últimos 30 dias?
            $exists = CrmMessage::where('tenant_id', $tenant->id)
                ->where('customer_id', $customer->id)
                ->where('channel', 'whatsapp')
                ->where('direction', 'outbound')
                ->where('body', 'like', "%calibração%{$eq->code}%")
                ->where('created_at', '>=', now()->subDays(30))
                ->exists();

            if ($exists) {
                continue;
            }

            $daysUntil = (int) now()->diffInDays($eq->next_calibration_at, false);
            $status = $daysUntil < 0 ? 'VENCIDA' : "vence em {$daysUntil} dias";

            try {
                if ($template) {
                    $service->sendFromTemplate($template, $customer, [
                        'nome' => $customer->name,
                        'equipamento' => "{$eq->brand} {$eq->model}",
                        'codigo' => $eq->code,
                        'status' => $status,
                    ]);
                } else {
                    $body = "Olá {$customer->name}! 📐\n\n";
                    $body .= "Informamos que a calibração do equipamento {$eq->brand} {$eq->model} (cód. {$eq->code}) {$status}.\n\n";
                    $body .= 'Entre em contato conosco para agendar. Estamos à disposição! 🛠️';

                    $service->sendWhatsApp($tenant->id, $customer, $body);
                }
                $count++;
                $this->messagesSent++;
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Erro ao enviar lembrete calibração para {$customer->name}: {$e->getMessage()}");
            }
        }

        if ($count > 0) {
            $this->line("  📱 {$count} lembrete(s) de calibração enviados via WhatsApp");
        }
    }

    /**
     * 8. Contrato expirando → Enviar e-mail de aviso
     */
    private function processContractMessages(Tenant $tenant): void
    {
        $count = 0;
        $service = app(MessagingService::class);

        $customers = Customer::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('contract_end')
            ->where('contract_end', '<=', now()->addDays(45))
            ->where('contract_end', '>=', now())
            ->whereNotNull('email')
            ->get();

        $template = CrmMessageTemplate::where('tenant_id', $tenant->id)
            ->where('slug', 'contrato-expirando')
            ->where('channel', 'email')
            ->active()
            ->first();

        foreach ($customers as $customer) {
            // Já enviou aviso nos últimos 30 dias?
            $exists = CrmMessage::where('tenant_id', $tenant->id)
                ->where('customer_id', $customer->id)
                ->where('channel', 'email')
                ->where('direction', 'outbound')
                ->where('body', 'like', '%contrato%renovação%')
                ->where('created_at', '>=', now()->subDays(30))
                ->exists();

            if ($exists) {
                continue;
            }

            $daysUntil = (int) now()->diffInDays($customer->contract_end, false);

            try {
                if ($template) {
                    $service->sendFromTemplate($template, $customer, [
                        'nome' => $customer->name,
                        'dias' => $daysUntil,
                        'data_vencimento' => $customer->contract_end->format('d/m/Y'),
                    ]);
                } else {
                    $subject = "Aviso: Seu contrato vence em {$daysUntil} dias";
                    $body = "Prezado(a) {$customer->name},\n\n";
                    $body .= "Gostaríamos de informar que o contrato de serviços vence em {$customer->contract_end->format('d/m/Y')} ({$daysUntil} dias).\n\n";
                    $body .= "Entre em contato conosco para discutir a renovação e condições especiais.\n\n";
                    $body .= "Atenciosamente,\nEquipe Técnica";

                    $service->sendEmail($tenant->id, $customer, $subject, $body);
                }
                $count++;
                $this->messagesSent++;
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Erro ao enviar aviso contrato para {$customer->name}: {$e->getMessage()}");
            }
        }

        if ($count > 0) {
            $this->line("  📧 {$count} aviso(s) de contrato enviados via e-mail");
        }
    }

    /**
     * Recalcular Health Score de todos os clientes ativos
     */
    private function recalculateHealthScores(Tenant $tenant): void
    {
        Customer::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    try {
                        $old = $customer->health_score;
                        $new = $customer->recalculateHealthScore();
                        if ($old !== $new) {
                            $this->customersUpdated++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("ProcessCrmAutomations: falha ao recalcular health score customer #{$customer->id}", ['error' => $e->getMessage()]);
                    }
                }
            });

        if ($this->customersUpdated > 0) {
            $this->line("  💚 {$this->customersUpdated} health score(s) recalculados");
        }
    }
}
