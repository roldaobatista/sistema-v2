<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Seed de TODAS as permissões granulares usadas nas rotas da API e no frontend.
     * Extraído de routes/api.php + frontend/src/App.tsx (routePermissionRules)
     */
    public function run(): void
    {
        $permissions = [
            // ─── AI & Analytics ───
            'ai.analytics.view',
            'analytics.dashboard.manage',
            'analytics.dashboard.view',
            'analytics.dataset.manage',
            'analytics.dataset.view',
            'analytics.export.create',
            'analytics.export.download',
            'analytics.export.view',

            // ─── Admin / Settings ───
            'admin.settings.manage',
            'admin.settings.update',
            'admin.settings.view',

            // ─── Automation ───
            'automation.rule.manage',
            'automation.rule.view',
            'automation.webhook.manage',
            'automation.webhook.view',

            // ─── Auvo ───
            'auvo.export.execute',
            'auvo.import.delete',
            'auvo.import.execute',
            'auvo.import.view',

            // ─── Cadastros ───
            'cadastros.customer.create',
            'cadastros.customer.delete',
            'cadastros.customer.update',
            'cadastros.customer.view',
            'cadastros.product.create',
            'cadastros.product.delete',
            'cadastros.product.update',
            'cadastros.product.view',
            'cadastros.service.create',
            'cadastros.service.delete',
            'cadastros.service.update',
            'cadastros.service.view',
            'cadastros.supplier.create',
            'cadastros.supplier.delete',
            'cadastros.supplier.update',
            'cadastros.supplier.view',

            // ─── Catálogo de Serviços (público) ───
            'catalog.view',
            'catalog.manage',

            // ─── Avançado (AdvancedFeatures) ───
            'advanced.follow_up.manage',
            'advanced.follow_up.view',
            'advanced.price_table.manage',
            'advanced.price_table.view',
            'advanced.cost_center.manage',
            'advanced.cost_center.view',
            'advanced.route_plan.view',

            // ─── Central de Atendimento ───
            'agenda.assign',
            'agenda.close.self',
            'agenda.close.any',
            'agenda.create.task',
            'agenda.item.view',
            'agenda.item.view_all',
            'agenda.item.create_for_others',
            'agenda.watcher.manage',
            'agenda.manage.kpis',
            'agenda.manage.rules',
            'agenda.notification.manage_global',

            // ─── Chamados (Service Calls) ───
            'service_calls.service_call.assign',
            'service_calls.service_call.create',
            'service_calls.service_call.delete',
            'service_calls.service_call.update',
            'service_calls.service_call.view',

            // ─── Comercial ───
            'comercial.view',
            'commercial.followup.manage',
            'commercial.followup.view',
            'commercial.price_table.manage',
            'commercial.price_table.view',

            // ─── Comissões ───
            'commissions.campaign.create',
            'commissions.campaign.delete',
            'commissions.campaign.update',
            'commissions.campaign.view',
            'commissions.dispute.create',
            'commissions.dispute.delete',
            'commissions.dispute.resolve',
            'commissions.dispute.view',
            'commissions.goal.create',
            'commissions.goal.delete',
            'commissions.goal.update',
            'commissions.goal.view',
            'commissions.recurring.create',
            'commissions.recurring.delete',
            'commissions.recurring.update',
            'commissions.recurring.view',
            'commissions.rule.create',
            'commissions.rule.delete',
            'commissions.rule.update',
            'commissions.rule.view',
            'commissions.event.update',
            'commissions.event.view',
            'commissions.settlement.approve',
            'commissions.settlement.create',
            'commissions.settlement.update',
            'commissions.settlement.view',

            // ─── CRM ───
            'crm.deal.create',
            'crm.deal.delete',
            'crm.deal.update',
            'crm.deal.view',
            'crm.forecast.view',
            'crm.form.manage',
            'crm.form.view',
            'crm.goal.manage',
            'crm.goal.view',
            'crm.message.send',
            'crm.message.view',
            'crm.pipeline.create',
            'crm.pipeline.delete',
            'crm.pipeline.update',
            'crm.pipeline.view',
            'crm.proposal.manage',
            'crm.proposal.view',
            'crm.referral.manage',
            'crm.referral.view',
            'crm.renewal.manage',
            'crm.renewal.view',
            'crm.scoring.manage',
            'crm.scoring.view',
            'crm.sequence.manage',
            'crm.sequence.view',
            'crm.territory.manage',
            'crm.territory.view',
            'crm.manage',
            'crm.view',

            // ─── Customer ───
            'customer.document.manage',
            'customer.document.view',
            'customer.nps.view',
            'customer.satisfaction.manage',
            'customer.satisfaction.view',

            // ─── Email ───
            'email.account.create',
            'email.account.delete',
            'email.account.sync',
            'email.account.update',
            'email.account.view',
            'email.inbox.create_task',
            'email.inbox.manage',
            'email.inbox.send',
            'email.inbox.view',
            'email.rule.create',
            'email.rule.delete',
            'email.rule.update',
            'email.rule.view',
            'email.signature.manage',
            'email.signature.view',
            'email.tag.manage',
            'email.tag.view',
            'email.template.create',
            'email.template.delete',
            'email.template.update',
            'email.template.view',

            // ─── Equipamentos ───
            'equipments.equipment.create',
            'equipments.equipment.delete',
            'equipments.equipment.update',
            'equipments.equipment.view',
            'equipments.calibration.create',
            'equipments.calibration.delete',
            'equipments.calibration.update',
            'equipments.calibration.view',
            'equipments.maintenance.create',
            'equipments.maintenance.delete',
            'equipments.maintenance.update',
            'equipments.maintenance.view',
            'equipments.document.create',
            'equipments.document.delete',
            'equipments.document.view',
            'equipments.standard_weight.create',
            'equipments.standard_weight.delete',
            'equipments.standard_weight.update',
            'equipments.standard_weight.view',
            'equipments.equipment_model.view',
            'equipments.equipment_model.create',
            'equipments.equipment_model.update',
            'equipments.equipment_model.delete',

            // ─── Estoque ───
            'estoque.manage',
            'estoque.movement.create',
            'estoque.movement.delete',
            'estoque.movement.update',
            'estoque.movement.view',
            'estoque.view',
            'estoque.warehouse.view',
            'estoque.warehouse.create',
            'estoque.warehouse.update',
            'estoque.warehouse.delete',
            'estoque.transfer.create',
            'estoque.transfer.accept',
            'estoque.transfer.view',
            'estoque.used_stock.view',
            'estoque.used_stock.report',
            'estoque.used_stock.confirm',
            'estoque.warranty.view',
            'estoque.warranty.create',
            'estoque.label.print',
            'estoque.serial.view',
            'estoque.serial.create',
            'estoque.rma.view',
            'estoque.rma.create',
            'estoque.disposal.view',
            'estoque.disposal.create',
            'estoque.intelligence.view',
            'estoque.kardex.view',
            'estoque.inventory.view',
            'estoque.inventory.create',
            'estoque.inventory.execute',

            // ─── Despesas / Abastecimento ───
            'expenses.expense.approve',
            'expenses.expense.create',
            'expenses.expense.delete',
            'expenses.expense.review',
            'expenses.expense.update',
            'expenses.expense.view',
            'expenses.fueling_log.approve',
            'expenses.fueling_log.create',
            'expenses.fueling_log.delete',
            'expenses.fueling_log.update',
            'expenses.fueling_log.view',

            // ─── Financeiro ───
            'finance.cashflow.view',
            'finance.collection_rule.manage',
            'finance.collection_rule.view',
            'finance.chart.create',
            'finance.chart.delete',
            'finance.chart.update',
            'finance.chart.view',
            'finance.cost_center.view',
            'finance.cost_center.manage',
            'finance.dre.view',
            'finance.payable.create',
            'finance.payable.delete',
            'finance.payable.settle',
            'finance.payable.update',
            'finance.payable.view',
            'finance.receivable.create',
            'finance.receivable.delete',
            'finance.receivable.settle',
            'finance.receivable.manage',
            'finance.receivable.update',
            'finance.receivable.view',
            'financeiro.accounts_receivable.create',
            'financeiro.accounts_receivable.update',
            'financeiro.accounts_receivable.view',
            'financeiro.approve',
            'financeiro.payment.create',
            'financeiro.view',
            'financial.bank_account.create',
            'financial.bank_account.delete',
            'financial.bank_account.update',
            'financial.bank_account.view',
            'financial.fund_transfer.cancel',
            'financial.fund_transfer.create',
            'financial.fund_transfer.view',

            // ─── Fiscal ───
            'fiscal.config.manage',
            'fiscal.config.view',
            'fiscal.note.cancel',
            'fiscal.note.create',
            'fiscal.note.view',

            // ─── LGPD ───
            'lgpd.consent.create',
            'lgpd.consent.revoke',
            'lgpd.consent.view',
            'lgpd.dpo.manage',
            'lgpd.dpo.view',
            'lgpd.incident.create',
            'lgpd.incident.update',
            'lgpd.incident.view',
            'lgpd.request.create',
            'lgpd.request.respond',
            'lgpd.request.view',
            'lgpd.treatment.create',
            'lgpd.treatment.delete',
            'lgpd.treatment.view',

            // ─── Billing ───
            'billing.plan.manage',
            'billing.plan.view',
            'billing.subscription.manage',
            'billing.subscription.view',

            // ─── Frota ───
            'fleet.fine.create',
            'fleet.fine.update',
            'fleet.fine.view',
            'fleet.inspection.create',
            'fleet.management',
            'fleet.tool_inventory.manage',
            'fleet.tool_inventory.view',
            'fleet.vehicle.create',
            'fleet.vehicle.delete',
            'fleet.vehicle.update',
            'fleet.vehicle.view',
            'fleet.view',

            // ─── RH ───
            'hr.adjustment.approve',
            'hr.adjustment.create',
            'hr.adjustment.view',
            'hr.analytics.view',
            'hr.benefits.manage',
            'hr.benefits.view',
            'hr.clock.approve',
            'hr.clock.manage',
            'hr.clock.view',
            'hr.dashboard.view',
            'hr.document.manage',
            'hr.document.view',
            'hr.epi.view',
            'hr.feedback.create',
            'hr.feedback.view',
            'hr.fiscal.access',
            'hr.geofence.manage',
            'hr.geofence.view',
            'hr.holiday.manage',
            'hr.holiday.view',
            'hr.journey.manage',
            'hr.journey.view',
            'hr.leave.approve',
            'hr.leave.create',
            'hr.leave.view',
            'hr.onboarding.manage',
            'hr.onboarding.view',
            'hr.organization.manage',
            'hr.organization.view',
            'hr.performance.manage',
            'hr.performance.view',
            'hr.performance.view_all',
            'hr.recruitment.manage',
            'hr.recruitment.view',
            'hr.reports.view',
            'hr.schedule.manage',
            'hr.schedule.view',
            'hr.dependent.manage',
            'hr.dependent.view',
            'hr.vacation.manage',
            'hr.vacation.view',
            'hr.hour_bank.manage',
            'hr.hour_bank.view',
            'hr.rescission.approve',
            'hr.rescission.manage',
            'hr.rescission.view',
            'hr.esocial.manage',
            'hr.esocial.view',
            'hr.payroll.manage',
            'hr.payroll.view',
            'rh.clock.manage',
            'rh.work_schedule.create',
            'rh.work_schedule.delete',
            'rh.work_schedule.update',
            'rh.work_schedule.view',

            // ─── RH Geral ───
            'rh.manage',
            'hr.skills.manage',
            'hr.skills.view',
            'hr.training.manage',
            'hr.training.view',

            // ─── IAM ───
            'iam.audit_log.export',
            'iam.audit_log.view',
            'iam.permission.manage',
            'iam.role.create',
            'iam.role.delete',
            'iam.role.update',
            'iam.role.view',
            'iam.user.create',
            'iam.user.delete',
            'iam.user.export',
            'iam.user.update',
            'iam.user.view',

            // ─── Importação ───
            'import.data.delete',
            'import.data.execute',
            'import.data.view',

            // ─── INMETRO ───
            'inmetro.intelligence.convert',
            'inmetro.intelligence.enrich',
            'inmetro.intelligence.import',
            'inmetro.intelligence.view',
            'inmetro.view',

            // ─── Selos de Reparo ───
            'repair_seals.view',
            'repair_seals.use',
            'repair_seals.manage',

            // ─── Notificações ───
            'notifications.notification.update',
            'notifications.notification.view',

            // ─── Ordens de Serviço ───
            'os.checklist.manage',
            'os.checklist.view',
            'os.maintenance_report.manage',
            'os.maintenance_report.view',
            'os.work_order.apply_discount',
            'os.work_order.authorize_dispatch',
            'os.work_order.change_status',
            'os.work_order.create',
            'os.work_order.delete',
            'os.work_order.export',
            'os.work_order.rating.view',
            'os.work_order.update',
            'os.work_order.view',

            // ─── Platform ───
            'platform.branch.create',
            'platform.branch.delete',
            'platform.branch.update',
            'platform.branch.view',
            'platform.dashboard.view',
            'platform.settings.manage',
            'platform.settings.view',
            'platform.tenant.create',
            'platform.tenant.delete',
            'platform.tenant.switch',
            'platform.tenant.update',
            'platform.tenant.view',
            'horizon.view',

            // ─── TV Dashboard ───
            'tv.dashboard.view',
            'tv.camera.manage',

            // ─── Portal ───
            'portal.client.create',
            'portal.client.view',
            'portal.view',

            // ─── Qualidade ───
            'qualidade.manage',
            'qualidade.view',
            'quality.complaint.manage',
            'quality.complaint.view',
            'quality.corrective_action.manage',
            'quality.corrective_action.view',
            'quality.dashboard.view',
            'quality.procedure.manage',
            'quality.procedure.view',
            'quality.procedure.create',
            'quality.procedure.update',
            'quality.audit.view',
            'quality.audit.create',
            'quality.audit.update',
            'quality.audit.delete',
            'quality.nc.view',
            'quality.nc.create',
            'quality.nc.update',
            'quality.nc.delete',
            'quality.document.view',
            'quality.document.create',
            'quality.document.update',
            'quality.document.approve',
            'quality.document.delete',
            'quality.management_review.view',
            'quality.management_review.create',
            'quality.management_review.update',

            // ─── Helpdesk ───
            'helpdesk.ticket_category.view',
            'helpdesk.ticket_category.manage',
            'helpdesk.escalation_rule.view',
            'helpdesk.escalation_rule.manage',

            // ─── Contracts ───
            'contracts.measurement.view',
            'contracts.measurement.manage',

            // ─── Procurement ───
            'procurement.supplier.view',
            'procurement.supplier.manage',

            // ─── Innovation ───
            'innovation.view',

            // ─── 75 Features — Permissões novas ───
            'equipments.manage',
            'equipments.view',
            'whatsapp.config.view',
            'whatsapp.config.manage',
            'whatsapp.log.view',
            'whatsapp.send',
            'alerts.alert.view',
            'alerts.view',
            'alerts.manage',
            'alerts.configure',
            'finance.renegotiation.view',
            'financeiro.renegotiation.view',
            'financeiro.renegotiation.create',
            'financeiro.renegotiation.approve',
            'financeiro.receipt.generate',
            'financeiro.collection.manage',
            'calibration.weight_assignment.view',
            'calibration.reading.create',
            'calibration.reading.view',
            'calibration.tool.view',
            'weight.assignment.view',
            'weight.assignment.manage',
            'tool.calibration.view',
            'tool.calibration.manage',
            'calibration.certificate.manage',
            'calibration.certificate.send',
            'calibration.template.manage',
            'accreditation.scope.manage',

            // ─── Orçamentos ───
            'quotes.quote.apply_discount',
            'quotes.quote.approve',
            'quotes.quote.convert',
            'quotes.quote.create',
            'quotes.quote.delete',
            'quotes.quote.export',
            'quotes.quote.internal_approve',
            'quotes.quote.invoice',
            'quotes.quote.send',
            'quotes.quote.update',
            'quotes.quote.view',

            // ─── Relatórios ───  (relatorios.report.view removida — permissão órfã sem uso)
            'reports.commission_report.export',
            'reports.commission_report.view',
            'reports.crm_report.export',
            'reports.crm_report.view',
            'reports.customers_report.export',
            'reports.customers_report.view',
            'reports.equipments_report.export',
            'reports.equipments_report.view',
            'reports.financial_report.export',
            'reports.financial_report.view',
            'reports.margin_report.export',
            'reports.margin_report.view',
            'reports.os_report.export',
            'reports.os_report.view',
            'reports.productivity_report.export',
            'reports.productivity_report.view',
            'reports.quotes_report.export',
            'reports.quotes_report.view',
            'reports.scheduled.manage',
            'reports.scheduled.view',
            'reports.service_calls_report.export',
            'reports.service_calls_report.view',
            'reports.stock_report.export',
            'reports.stock_report.view',
            'reports.suppliers_report.export',
            'reports.suppliers_report.view',
            'reports.technician_cash_report.export',
            'reports.technician_cash_report.view',

            // ─── Reports Analytics ───
            'reports.analytics.view',

            // ─── Contratos ───
            'contracts.contract.create',
            'contracts.contract.delete',
            'contracts.contract.update',
            'contracts.contract.view',

            // ─── Rotas ───
            'route.plan.manage',
            'route.plan.view',

            // ─── Técnicos ───
            'technicians.cashbox.expense.create',
            'technicians.cashbox.expense.delete',
            'technicians.cashbox.expense.update',
            'technicians.cashbox.manage',
            'technicians.cashbox.request_funds',
            'technicians.cashbox.view',
            'technicians.checklist.create',
            'technicians.checklist.manage',
            'technicians.checklist.view',
            'technicians.schedule.manage',
            'technicians.schedule.view',
            'technicians.time_entry.create',
            'technicians.time_entry.delete',
            'technicians.time_entry.update',
            'technicians.time_entry.view',

            // ─── Avançado (Frontend route: /avancado) ───
            'advanced.follow_up.view',

            // ─── Cadastros Auxiliares (Lookups) ───
            'lookups.view',
            'lookups.create',
            'lookups.update',
            'lookups.delete',
        ];

        $guard = (string) config('auth.defaults.guard', 'web');

        // Bulk upsert para velocidade (testes com SQLite usam insertOrIgnore)
        $now = now();
        $rows = array_map(fn ($p) => [
            'name' => $p,
            'guard_name' => $guard,
            'created_at' => $now,
            'updated_at' => $now,
        ], $permissions);

        $created = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            $created += DB::table(
                config('permission.table_names.permissions', 'permissions')
            )->insertOrIgnore($chunk);
        }

        // ─── Roles do sistema com nomes em português ───

        $roles = [
            ['name' => 'super_admin',       'display_name' => 'Super Administrador',  'description' => 'Acesso total ao sistema, sem restrições.'],
            ['name' => 'admin',             'display_name' => 'Administrador',        'description' => 'Gerencia tudo exceto configurações de plataforma.'],
            ['name' => 'gerente',           'display_name' => 'Gerente',              'description' => 'Gestão operacional e financeira completa.'],
            ['name' => 'coordenador',       'display_name' => 'Coordenador Técnico',  'description' => 'Coordena equipe técnica, agenda e chamados.'],
            ['name' => 'tecnico',           'display_name' => 'Técnico',              'description' => 'Executa ordens de serviço e checklists em campo.'],
            ['name' => 'financeiro',        'display_name' => 'Financeiro',           'description' => 'Contas a pagar/receber, faturamento e conciliação.'],
            ['name' => 'comercial',         'display_name' => 'Comercial / Vendas',   'description' => 'Orçamentos, CRM, pipeline de vendas.'],
            ['name' => 'atendimento',       'display_name' => 'Atendimento',          'description' => 'Central de atendimento, chamados e portal do cliente.'],
            ['name' => 'rh',                'display_name' => 'Recursos Humanos',     'description' => 'Gestão de ponto, jornada, férias e documentos.'],
            ['name' => 'estoquista',        'display_name' => 'Estoquista',           'description' => 'Movimentações de estoque, inventários e armazéns.'],
            ['name' => 'qualidade',         'display_name' => 'Qualidade',            'description' => 'Procedimentos, ações corretivas e NPS.'],
            ['name' => 'visualizador',      'display_name' => 'Visualizador',         'description' => 'Acesso somente leitura a todos os módulos.'],
            ['name' => 'monitor',           'display_name' => 'Monitor',              'description' => 'Acesso ao dashboard TV e câmeras de monitoramento.'],
            ['name' => 'vendedor',          'display_name' => 'Vendedor',             'description' => 'Vendas, orçamentos e prospecção de clientes.'],
            ['name' => 'tecnico_vendedor',  'display_name' => 'Técnico-Vendedor',     'description' => 'Acumula funções de técnico e vendedor com acesso a valores.'],
            ['name' => 'motorista',         'display_name' => 'Motorista',            'description' => 'Operação da UMC, despesas e abastecimento.'],
        ];
        $roleTable = config('permission.table_names.roles', 'roles');
        $now = now();

        // Sempre usar global null roles no Seeder para sincronizar.
        foreach ($roles as $r) {
            Role::firstOrCreate(
                [
                    'name' => $r['name'],
                    'guard_name' => $guard,
                    'tenant_id' => null,
                ],
                [
                    'display_name' => $r['display_name'],
                    'description' => $r['description'],
                ]
            );
        }

        // ─── Atribuição de permissões por role ───

        // Mapeamento de role para filtro de permissões
        $rolePermissionFilters = [
            'super_admin' => fn ($p) => true, // TUDO
            'admin' => fn ($p) => ! str_starts_with($p, 'platform.tenant') && $p !== 'iam.permission.manage',
            'gerente' => fn ($p) => ! str_starts_with($p, 'platform.tenant') && $p !== 'iam.permission.manage' && $p !== 'admin.settings.update',
            'coordenador' => fn ($p) => str_starts_with($p, 'os.') || str_starts_with($p, 'service_calls.') || str_starts_with($p, 'chamados.') ||
                str_starts_with($p, 'technicians.') || str_starts_with($p, 'route.plan.') || str_starts_with($p, 'equipments.') ||
                str_starts_with($p, 'cadastros.customer.view') || str_starts_with($p, 'cadastros.product.view') ||
                str_starts_with($p, 'cadastros.service.view') || str_starts_with($p, 'catalog.') ||
                str_starts_with($p, 'estoque.movement.view') || str_starts_with($p, 'notifications.') ||
                $p === 'platform.dashboard.view' || $p === 'hr.clock.view' || $p === 'hr.schedule.view',
            'financeiro' => fn ($p) => str_starts_with($p, 'finance.') || str_starts_with($p, 'financial.') || str_starts_with($p, 'financeiro.') ||
                str_starts_with($p, 'expenses.') || str_starts_with($p, 'commissions.') || str_starts_with($p, 'fiscal.') ||
                str_starts_with($p, 'reports.financial') || str_starts_with($p, 'reports.commission') ||
                str_starts_with($p, 'reports.margin') || str_starts_with($p, 'reports.technician_cash') ||
                in_array($p, ['cadastros.customer.view', 'cadastros.product.view', 'cadastros.service.view',
                    'cadastros.supplier.view', 'quotes.quote.view', 'os.work_order.view',
                    'notifications.notification.view', 'platform.dashboard.view',
                    'agenda.item.view', 'agenda.manage.kpis', 'agenda.manage.rules']),
            'comercial' => fn ($p) => str_starts_with($p, 'crm.') || str_starts_with($p, 'quotes.') || str_starts_with($p, 'comercial.') ||
                str_starts_with($p, 'commercial.') || str_starts_with($p, 'cadastros.customer.') ||
                str_starts_with($p, 'customer.') || str_starts_with($p, 'cadastros.product.view') ||
                str_starts_with($p, 'cadastros.service.view') || str_starts_with($p, 'catalog.') ||
                str_starts_with($p, 'reports.crm') || str_starts_with($p, 'reports.quotes') ||
                str_starts_with($p, 'reports.customers') ||
                in_array($p, ['os.work_order.view', 'notifications.notification.view', 'notifications.notification.update', 'platform.dashboard.view']),
            'atendimento' => fn ($p) => str_starts_with($p, 'agenda.') || str_starts_with($p, 'service_calls.') || str_starts_with($p, 'chamados.') ||
                str_starts_with($p, 'portal.') || str_starts_with($p, 'email.inbox.') ||
                in_array($p, ['cadastros.customer.view', 'cadastros.customer.create', 'os.work_order.view', 'quotes.quote.view',
                    'notifications.notification.view', 'notifications.notification.update', 'platform.dashboard.view']),
            'rh' => fn ($p) => str_starts_with($p, 'hr.') || in_array($p, ['iam.user.view', 'notifications.notification.view', 'platform.dashboard.view']),
            'estoquista' => fn ($p) => str_starts_with($p, 'estoque.') || str_starts_with($p, 'reports.stock') ||
                in_array($p, ['cadastros.product.view', 'cadastros.supplier.view', 'notifications.notification.view', 'platform.dashboard.view']),
            'qualidade' => fn ($p) => str_starts_with($p, 'quality.') || str_starts_with($p, 'qualidade.') ||
                str_starts_with($p, 'customer.nps') || str_starts_with($p, 'customer.satisfaction') ||
                in_array($p, ['os.work_order.view', 'cadastros.customer.view', 'equipments.equipment.view', 'notifications.notification.view', 'platform.dashboard.view']),
            'visualizador' => fn ($p) => str_ends_with($p, '.view'),
            'monitor' => fn ($p) => str_starts_with($p, 'tv.') || in_array($p, ['platform.dashboard.view', 'os.work_order.view', 'service_calls.service_call.view']),
        ];

        // Permissões explícitas para roles que não usam filtros
        $roleExplicitPerms = [
            'tecnico' => [
                'os.work_order.view', 'os.work_order.update', 'os.work_order.change_status',
                'technicians.schedule.view', 'technicians.time_entry.view', 'technicians.time_entry.create',
                'technicians.checklist.view', 'technicians.cashbox.view', 'route.plan.view',
                'service_calls.service_call.view', 'service_calls.service_call.update',
                'equipments.equipment.view', 'equipments.equipment_model.view',
                'os.maintenance_report.view', 'os.maintenance_report.manage',
                'calibration.certificate.manage',
                'estoque.view', 'estoque.movement.view', 'estoque.transfer.accept',
                'estoque.used_stock.view', 'estoque.used_stock.report',
                'cadastros.customer.view', 'cadastros.product.view', 'cadastros.service.view', 'catalog.view',
                'notifications.notification.view', 'notifications.notification.update',
                'hr.clock.view', 'hr.clock.manage', 'agenda.item.view', 'agenda.create.task', 'agenda.close.self',
            ],
            'vendedor' => null, // usa filtro
            'tecnico_vendedor' => null, // usa filtro
            'motorista' => [
                'os.work_order.view', 'os.work_order.update', 'os.work_order.change_status',
                'expenses.expense.create', 'expenses.expense.view', 'expenses.fueling_log.create', 'expenses.fueling_log.view',
                'fleet.vehicle.view', 'fleet.view', 'technicians.cashbox.view', 'technicians.schedule.view', 'route.plan.view',
                'cadastros.customer.view', 'notifications.notification.view', 'notifications.notification.update',
                'hr.clock.view', 'hr.clock.manage', 'estoque.view', 'estoque.transfer.create', 'estoque.transfer.accept', 'estoque.movement.view',
            ],
        ];

        // vendedor e tecnico_vendedor usam filtros
        $rolePermissionFilters['vendedor'] = fn ($p) => str_starts_with($p, 'crm.') || str_starts_with($p, 'quotes.') || str_starts_with($p, 'comercial.') ||
            str_starts_with($p, 'commercial.') || str_starts_with($p, 'cadastros.customer.') || str_starts_with($p, 'customer.') ||
            str_starts_with($p, 'cadastros.product.view') || str_starts_with($p, 'cadastros.service.view') ||
            str_starts_with($p, 'catalog.') || str_starts_with($p, 'reports.crm') || str_starts_with($p, 'reports.quotes') ||
            str_starts_with($p, 'reports.customers') ||
            in_array($p, ['service_calls.service_call.view', 'service_calls.service_call.create', 'os.work_order.view',
                'notifications.notification.view', 'notifications.notification.update', 'platform.dashboard.view']);

        $rolePermissionFilters['tecnico_vendedor'] = fn ($p) => str_starts_with($p, 'os.work_order.') || str_starts_with($p, 'technicians.') || str_starts_with($p, 'service_calls.') ||
            str_starts_with($p, 'equipments.equipment.view') || str_starts_with($p, 'estoque.movement.view') || str_starts_with($p, 'hr.clock.') ||
            str_starts_with($p, 'crm.') || str_starts_with($p, 'quotes.') || str_starts_with($p, 'comercial.') ||
            str_starts_with($p, 'commercial.') || str_starts_with($p, 'cadastros.customer.') || str_starts_with($p, 'customer.') ||
            str_starts_with($p, 'cadastros.product.') || str_starts_with($p, 'cadastros.service.') || str_starts_with($p, 'catalog.') ||
            str_starts_with($p, 'reports.crm') || str_starts_with($p, 'reports.quotes') || str_starts_with($p, 'reports.customers') ||
            in_array($p, ['expenses.expense.create', 'expenses.expense.view', 'notifications.notification.view', 'notifications.notification.update', 'platform.dashboard.view']);

        // Carregar permissões e roles do banco
        $allPerms = Permission::where('guard_name', $guard)->get();
        $permIdByName = $allPerms->pluck('id', 'name');
        $allRoles = Role::where('guard_name', $guard)->get()->keyBy('name');

        // Bulk insert na tabela pivot role_has_permissions
        $pivotTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $pivotRows = [];

        foreach ($allRoles as $roleName => $role) {
            $rolePerms = [];

            if (isset($roleExplicitPerms[$roleName])) {
                $rolePerms = $roleExplicitPerms[$roleName];
            } elseif (isset($rolePermissionFilters[$roleName])) {
                $rolePerms = array_values(array_filter($permissions, $rolePermissionFilters[$roleName]));
            }

            foreach ($rolePerms as $perm) {
                if (isset($permIdByName[$perm])) {
                    $pivotRows[] = [
                        'permission_id' => $permIdByName[$perm],
                        'role_id' => $role->id,
                    ];
                }
            }
        }

        // Insere permissões dos roles (ignora se já existem)
        foreach (array_chunk($pivotRows, 500) as $chunk) {
            DB::table($pivotTable)->insertOrIgnore($chunk);
        }

        // Limpa cache do Spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('✅ '.count($permissions)." permissões criadas/verificadas ({$created} novas)");
        $this->command->info('✅ '.count($roles).' roles configurados com nomes em português');
        $this->command->info('✅ Cada role recebeu permissões adequadas ao seu perfil');
    }
}
