<?php

namespace Tests\Feature\Api\V1;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginationContractTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function cappedControllerProvider(): array
    {
        return [
            'organization' => ['app/Http/Controllers/Api/V1/OrganizationController.php'],
            'payroll' => ['app/Http/Controllers/Api/V1/PayrollController.php'],
            'technician_cash' => ['app/Http/Controllers/Api/V1/TechnicianCashController.php'],
            'clt_violation' => ['app/Http/Controllers/Api/V1/Hr/CltViolationController.php'],
            'analytics_dataset' => ['app/Http/Controllers/Api/V1/Analytics/AnalyticsDatasetController.php'],
            'embedded_dashboard' => ['app/Http/Controllers/Api/V1/Analytics/EmbeddedDashboardController.php'],
            'data_export_job' => ['app/Http/Controllers/Api/V1/Analytics/DataExportJobController.php'],
            'email_activity' => ['app/Http/Controllers/Api/V1/Email/EmailActivityController.php'],
            'lgpd_consent_log' => ['app/Http/Controllers/Api/V1/Lgpd/LgpdConsentLogController.php'],
            'lgpd_data_request' => ['app/Http/Controllers/Api/V1/Lgpd/LgpdDataRequestController.php'],
            'lgpd_data_treatment' => ['app/Http/Controllers/Api/V1/Lgpd/LgpdDataTreatmentController.php'],
            'stock_movement' => ['app/Http/Controllers/Api/V1/StockMovementController.php'],
            'portal' => ['app/Http/Controllers/Api/V1/Portal/PortalController.php'],
            'department' => ['app/Http/Controllers/Api/V1/DepartmentController.php'],
            'email_log' => ['app/Http/Controllers/Api/V1/EmailLogController.php'],
            'fiscal_invoice' => ['app/Http/Controllers/Api/V1/FiscalInvoiceController.php'],
            'saas_plan' => ['app/Http/Controllers/Api/V1/Billing/SaasPlanController.php'],
            'saas_subscription' => ['app/Http/Controllers/Api/V1/Billing/SaasSubscriptionController.php'],
            'role' => ['app/Http/Controllers/Api/V1/Iam/RoleController.php'],
            'work_order_signature' => ['app/Http/Controllers/Api/V1/Os/WorkOrderSignatureController.php'],
            'work_order_time_log' => ['app/Http/Controllers/Api/V1/Os/WorkOrderTimeLogController.php'],
            'lgpd_security_incident' => ['app/Http/Controllers/Api/V1/Lgpd/LgpdSecurityIncidentController.php'],
        ];
    }

    #[DataProvider('cappedControllerProvider')]
    public function test_controller_listings_cap_client_supplied_per_page(string $relativePath): void
    {
        $source = file_get_contents(base_path($relativePath));

        $this->assertIsString($source);
        $this->assertDoesNotMatchRegularExpression(
            '/paginate\(\s*(?:\(int\)\s*)?\$request->(?:integer|get|input|query)\(/',
            $source,
            "{$relativePath} usa per_page do cliente sem teto maximo."
        );
    }

    #[DataProvider('cappedControllerProvider')]
    public function test_controller_listings_enforce_positive_per_page_floor(string $relativePath): void
    {
        $source = file_get_contents(base_path($relativePath));

        $this->assertIsString($source);
        $this->assertDoesNotMatchRegularExpression(
            '/->paginate\(\s*min\(/',
            $source,
            "{$relativePath} aplica teto de per_page sem piso minimo positivo."
        );
    }

    public function test_portal_controller_does_not_build_nested_empty_pagination_envelope(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/V1/Portal/PortalController.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            "ApiResponse::data(['data' => [], 'meta' => ['total' => 0]])",
            $source,
            'PortalController deve retornar envelope canonico vazio, nao data.data/meta manual.'
        );
        $this->assertStringNotContainsString(
            'return ApiResponse::data([])',
            $source,
            'PortalController deve retornar paginador vazio canonico, nao lista solta.'
        );
    }

    public function test_portal_certificates_endpoint_uses_capped_pagination_contract(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/V1/Portal/PortalController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'return ApiResponse::paginated($this->emptyPaginator($request, 20))',
            $source,
            'PortalController::certificates deve devolver envelope paginado vazio quando a tabela nao existir.'
        );
        $this->assertStringContainsString(
            "->paginate(max(1, min(\$request->integer('per_page', 20), 100)))",
            $source,
            'PortalController::certificates deve paginar com teto e piso de per_page.'
        );
        $this->assertStringNotContainsString(
            'return ApiResponse::data($certificates)',
            $source,
            'PortalController::certificates nao deve devolver colecao integral sem meta de paginacao.'
        );
    }
}
