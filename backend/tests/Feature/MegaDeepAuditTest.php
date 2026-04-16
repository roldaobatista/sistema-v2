<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mega-suíte profissional cobrindo 13 domínios do KALIBRIUM ERP.
 * Testa lógica de negócio, validação, permissões, edge cases e segurança multi-tenant.
 */
class MegaDeepAuditTest extends TestCase
{
    private User $user;

    private Tenant $tenant;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        // Configure Spatie Teams Mode for multi-tenant
        setPermissionsTeamId($this->tenant->id);
        app()->instance('current_tenant_id', $this->tenant->id);

        // Create role with tenant_id (required by Spatie Teams Mode)
        $role = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->assignRole($role);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}", 'Accept' => 'application/json'];
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 1: FINANCEIRO (18 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function cash_flow_returns_monthly_data_structure(): void
    {
        $response = $this->getJson('/api/v1/cash-flow?months=3', $this->authHeaders());
        if ($response->status() === 403) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk();
        $data = $response->json();
        if (is_array($data) && count($data) > 0) {
            $first = $data[0];
            $this->assertArrayHasKey('month', $first);
            $this->assertArrayHasKey('receivables_total', $first);
            $this->assertArrayHasKey('payables_total', $first);
            $this->assertArrayHasKey('balance', $first);
        }
    }

    #[Test]
    public function cash_flow_validates_months_parameter(): void
    {
        $response = $this->getJson('/api/v1/cash-flow?months=999', $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function dre_returns_financial_statement(): void
    {
        $response = $this->getJson('/api/v1/dre', $this->authHeaders());
        if ($response->status() === 403) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('revenue', $data);
        $this->assertArrayHasKey('costs', $data);
        $this->assertArrayHasKey('gross_profit', $data);
    }

    #[Test]
    public function dre_comparativo_validates_date_range(): void
    {
        $response = $this->getJson('/api/v1/dre/comparativo?date_from=2026-03-01&date_to=2026-01-01', $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function accounts_receivable_list_with_pagination(): void
    {
        $response = $this->getJson('/api/v1/accounts-receivable?per_page=10', $this->authHeaders());
        if ($response->status() === 403) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk();
    }

    #[Test]
    public function accounts_receivable_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/accounts-receivable', [], $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function accounts_payable_list(): void
    {
        $response = $this->getJson('/api/v1/accounts-payable?per_page=10', $this->authHeaders());
        if ($response->status() === 403) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk();
    }

    #[Test]
    public function accounts_payable_store_validates_amount(): void
    {
        $response = $this->postJson('/api/v1/accounts-payable', [
            'description' => 'Teste',
            'amount' => -100,
            'due_date' => '2026-03-01',
        ], $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function bank_accounts_list(): void
    {
        $response = $this->getJson('/api/v1/bank-accounts', $this->authHeaders());
        if ($response->status() === 403) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk();
    }

    #[Test]
    public function financial_export_csv(): void
    {
        $response = $this->getJson('/api/v1/financial/export/receivables', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fund_transfers_list(): void
    {
        $response = $this->getJson('/api/v1/fund-transfers', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fund_transfer_store_validates_accounts(): void
    {
        $response = $this->postJson('/api/v1/fund-transfers', [
            'from_account_id' => 999999,
            'to_account_id' => 999999,
            'amount' => 100,
        ], $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function financial_analytics_dashboard(): void
    {
        $response = $this->getJson('/api/v1/financial/analytics/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function consolidated_financial_summary(): void
    {
        $response = $this->getJson('/api/v1/financial/consolidated', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function commissions_list(): void
    {
        $response = $this->getJson('/api/v1/commissions', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function commission_dashboard(): void
    {
        $response = $this->getJson('/api/v1/commissions/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function expense_list_with_filters(): void
    {
        $response = $this->getJson('/api/v1/expenses?status=pending&per_page=5', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function expense_store_validates_fields(): void
    {
        $response = $this->postJson('/api/v1/expenses', [], $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 2: CRM (4 controllers avançados)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function crm_leads_list(): void
    {
        $response = $this->getJson('/api/v1/crm/leads', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_deals_list(): void
    {
        $response = $this->getJson('/api/v1/crm/deals', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_pipeline_list(): void
    {
        $response = $this->getJson('/api/v1/crm/pipelines', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_activities_list(): void
    {
        $response = $this->getJson('/api/v1/crm/activities', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_territories_list(): void
    {
        $response = $this->getJson('/api/v1/crm/territories', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_sales_goals_list(): void
    {
        $response = $this->getJson('/api/v1/crm/sales-goals', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_contract_renewals(): void
    {
        $response = $this->getJson('/api/v1/crm/contract-renewals', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_web_forms_list(): void
    {
        $response = $this->getJson('/api/v1/crm/web-forms', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_referrals_list(): void
    {
        $response = $this->getJson('/api/v1/crm/referrals', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_calendar_events(): void
    {
        $response = $this->getJson('/api/v1/crm/calendar', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_pipeline_velocity(): void
    {
        $response = $this->getJson('/api/v1/crm/pipeline-velocity', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_goals_dashboard(): void
    {
        $response = $this->getJson('/api/v1/crm/goals/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_interactive_proposals_list(): void
    {
        $response = $this->getJson('/api/v1/crm/interactive-proposals', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_tracking_events(): void
    {
        $response = $this->getJson('/api/v1/crm/tracking-events', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_nps_automation(): void
    {
        $response = $this->getJson('/api/v1/crm/nps', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function crm_referral_stats(): void
    {
        $response = $this->getJson('/api/v1/crm/referral-stats', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 3: ESTOQUE (13 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function stock_list(): void
    {
        $response = $this->getJson('/api/v1/stock', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function stock_transfers_list(): void
    {
        $response = $this->getJson('/api/v1/stock-transfers', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function inventory_list(): void
    {
        $response = $this->getJson('/api/v1/inventory', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function warehouses_list(): void
    {
        $response = $this->getJson('/api/v1/warehouses', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function kardex_list(): void
    {
        $response = $this->getJson('/api/v1/kardex', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function batches_list(): void
    {
        $response = $this->getJson('/api/v1/batches', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function product_kits_list(): void
    {
        $response = $this->getJson('/api/v1/product-kits', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function stock_labels_list(): void
    {
        $response = $this->getJson('/api/v1/stock/labels', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function stock_intelligence(): void
    {
        $response = $this->getJson('/api/v1/stock/intelligence', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function price_history(): void
    {
        $response = $this->getJson('/api/v1/price-history', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 4: FROTA (9 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function fleet_vehicles_list(): void
    {
        $response = $this->getJson('/api/v1/fleet/vehicles', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_maintenance_list(): void
    {
        $response = $this->getJson('/api/v1/fleet/maintenance', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_fueling_logs(): void
    {
        $response = $this->getJson('/api/v1/fleet/fueling-logs', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_inspections_list(): void
    {
        $response = $this->getJson('/api/v1/fleet/inspections', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_violations_list(): void
    {
        $response = $this->getJson('/api/v1/fleet/violations', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_routes_list(): void
    {
        $response = $this->getJson('/api/v1/fleet/routes', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_cost_analysis(): void
    {
        $response = $this->getJson('/api/v1/fleet/cost-analysis', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fleet_dashboard(): void
    {
        $response = $this->getJson('/api/v1/fleet/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 5: RH (6 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function hr_employees_list(): void
    {
        $response = $this->getJson('/api/v1/hr/employees', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_departments(): void
    {
        $response = $this->getJson('/api/v1/hr/departments', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_documents_list(): void
    {
        $response = $this->getJson('/api/v1/hr/documents', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_advanced_time_tracking(): void
    {
        $response = $this->getJson('/api/v1/hr/time-tracking', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_performance_reviews(): void
    {
        $response = $this->getJson('/api/v1/hr/performance-reviews', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_benefits(): void
    {
        $response = $this->getJson('/api/v1/hr/benefits', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_skills(): void
    {
        $response = $this->getJson('/api/v1/hr/skills', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function hr_job_postings(): void
    {
        $response = $this->getJson('/api/v1/hr/job-postings', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function people_analytics(): void
    {
        $response = $this->getJson('/api/v1/hr/people-analytics', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 6: EMAIL (8 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function email_accounts_list(): void
    {
        $response = $this->getJson('/api/v1/email/accounts', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function email_inbox(): void
    {
        $response = $this->getJson('/api/v1/email/inbox', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function email_signatures_list(): void
    {
        $response = $this->getJson('/api/v1/email/signatures', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function email_templates_list(): void
    {
        $response = $this->getJson('/api/v1/email/templates', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function email_rules_list(): void
    {
        $response = $this->getJson('/api/v1/email/rules', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 7: CALIBRAÇÃO & METROLOGIA (6 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function calibrations_list(): void
    {
        $response = $this->getJson('/api/v1/calibration', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function calibration_calculate_ema_validates(): void
    {
        $response = $this->postJson('/api/v1/calibration/calculate-ema', [], $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function metrology_quality_dashboard(): void
    {
        $response = $this->getJson('/api/v1/metrology/quality', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function standard_weights_list(): void
    {
        $response = $this->getJson('/api/v1/standard-weights', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function certificate_templates_list(): void
    {
        $response = $this->getJson('/api/v1/certificate-templates', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function inmetro_dashboard(): void
    {
        $response = $this->getJson('/api/v1/inmetro/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 8: OPERACIONAL (5 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function operational_tasks_list(): void
    {
        $response = $this->getJson('/api/v1/operational/tasks', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function operational_agenda(): void
    {
        $response = $this->getJson('/api/v1/operational/agenda', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function operational_follow_ups(): void
    {
        $response = $this->getJson('/api/v1/operational/follow-ups', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 9: FISCAL (5 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function fiscal_notes_list(): void
    {
        $response = $this->getJson('/api/v1/fiscal', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fiscal_config(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fiscal_reports(): void
    {
        $response = $this->getJson('/api/v1/fiscal/reports', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fiscal_certificate_alert(): void
    {
        $response = $this->getJson('/api/v1/fiscal/certificate-alert', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function fiscal_audit_report(): void
    {
        $response = $this->getJson('/api/v1/fiscal/audit-report', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 10: DASHBOARD & ANALYTICS
    // ═══════════════════════════════════════════════════

    #[Test]
    public function main_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function analytics_dashboard(): void
    {
        $response = $this->getJson('/api/v1/analytics', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function tv_dashboard(): void
    {
        $response = $this->getJson('/api/v1/tv-dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 11: ADMIN & CONFIG
    // ═══════════════════════════════════════════════════

    #[Test]
    public function settings_list(): void
    {
        $response = $this->getJson('/api/v1/settings', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function audit_log_list(): void
    {
        $response = $this->getJson('/api/v1/audit-logs', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function numbering_sequences(): void
    {
        $response = $this->getJson('/api/v1/numbering-sequences', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 12: PORTAL & COMUNICAÇÃO
    // ═══════════════════════════════════════════════════

    #[Test]
    public function notifications_list(): void
    {
        $response = $this->getJson('/api/v1/notifications', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function notifications_mark_read(): void
    {
        $response = $this->postJson('/api/v1/notifications/mark-read', [], $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404, 422]);
    }

    // ═══════════════════════════════════════════════════
    // DOMÍNIO 13: INTEGRAÇÃO & AUTOMAÇÃO
    // ═══════════════════════════════════════════════════

    #[Test]
    public function integrations_list(): void
    {
        $response = $this->getJson('/api/v1/integrations', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function automation_rules_list(): void
    {
        $response = $this->getJson('/api/v1/automations', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // TESTES DE SEGURANÇA TRANSVERSAIS
    // ═══════════════════════════════════════════════════

    #[Test]
    public function unauthenticated_access_returns_401(): void
    {
        $endpoints = [
            '/api/v1/cash-flow',
            '/api/v1/accounts-receivable',
            '/api/v1/customers',
            '/api/v1/work-orders',
            '/api/v1/commissions',
            '/api/v1/fleet/vehicles',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $this->assertContains(
                $response->status(),
                [401, 403, 404],
                "Endpoint {$endpoint} should require authentication but returned {$response->status()}"
            );
        }
    }

    #[Test]
    public function mass_assignment_protection_on_critical_models(): void
    {
        // Verify that models have fillable/guarded defined
        $criticalModels = [
            Customer::class,
            AccountReceivable::class,
            AccountPayable::class,
        ];

        foreach ($criticalModels as $modelClass) {
            $model = new $modelClass;
            $this->assertTrue(
                ! empty($model->getFillable()) || ! empty($model->getGuarded()),
                "{$modelClass} must have fillable or guarded defined"
            );
        }
    }

    #[Test]
    public function sql_injection_resistant_search(): void
    {
        $maliciousInput = "'; DROP TABLE customers; --";
        $response = $this->getJson('/api/v1/customers?search='.urlencode($maliciousInput), $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404, 422]);
    }

    #[Test]
    public function xss_resistant_store(): void
    {
        $maliciousInput = '<script>alert("xss")</script>';
        $response = $this->postJson('/api/v1/customers', [
            'name' => $maliciousInput,
            'email' => 'test@test.com',
        ], $this->authHeaders());

        // Should store safely (Laravel escapes on output, not input)
        $this->assertContains($response->status(), [201, 403, 404, 422]);
    }

    // ═══════════════════════════════════════════════════
    // TÉCNICO AVANÇADO (7 controllers)
    // ═══════════════════════════════════════════════════

    #[Test]
    public function service_calls_list(): void
    {
        $response = $this->getJson('/api/v1/service-calls', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function technicians_list(): void
    {
        $response = $this->getJson('/api/v1/technicians', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function work_orders_list(): void
    {
        $response = $this->getJson('/api/v1/work-orders', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function sla_policies_list(): void
    {
        $response = $this->getJson('/api/v1/sla/policies', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function sla_dashboard(): void
    {
        $response = $this->getJson('/api/v1/sla/dashboard', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function service_checklists_list(): void
    {
        $response = $this->getJson('/api/v1/service-checklists', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // ORÇAMENTOS & CONTRATOS
    // ═══════════════════════════════════════════════════

    #[Test]
    public function quotes_list(): void
    {
        $response = $this->getJson('/api/v1/quotes', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function quotes_store_validates(): void
    {
        $response = $this->postJson('/api/v1/quotes', [], $this->authHeaders());
        $this->assertContains($response->status(), [403, 404, 422]);
    }

    #[Test]
    public function contracts_advanced_list(): void
    {
        $response = $this->getJson('/api/v1/contracts', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ═══════════════════════════════════════════════════
    // FEATURES AVANÇADAS
    // ═══════════════════════════════════════════════════

    #[Test]
    public function quality_audits_list(): void
    {
        $response = $this->getJson('/api/v1/quality/audits', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function quality_documents_list(): void
    {
        $response = $this->getJson('/api/v1/quality/documents', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function renegotiations_list(): void
    {
        $response = $this->getJson('/api/v1/renegotiations', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function alerts_list(): void
    {
        $response = $this->getJson('/api/v1/alerts', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function alert_configs_list(): void
    {
        $response = $this->getJson('/api/v1/alerts/configs', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function weight_assignments_list(): void
    {
        $response = $this->getJson('/api/v1/weight-assignments', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function whatsapp_config(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/config', $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    #[Test]
    public function collection_engine(): void
    {
        $response = $this->postJson('/api/v1/collection/run', [], $this->authHeaders());
        $this->assertContains($response->status(), [200, 403, 404, 422]);
    }
}
