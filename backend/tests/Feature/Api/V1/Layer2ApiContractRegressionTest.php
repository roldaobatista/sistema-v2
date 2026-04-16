<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Layer2ApiContractRegressionTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function correctedControllerPaths(): array
    {
        return [
            'app/Http/Controllers/Api/V1/Analytics/AnalyticsDatasetController.php',
            'app/Http/Controllers/Api/V1/Analytics/DataExportJobController.php',
            'app/Http/Controllers/Api/V1/Analytics/EmbeddedDashboardController.php',
            'app/Http/Controllers/Api/V1/Email/EmailActivityController.php',
            'app/Http/Controllers/Api/V1/Hr/CltViolationController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdConsentLogController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdDataRequestController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdDataTreatmentController.php',
            'app/Http/Controllers/Api/V1/OrganizationController.php',
            'app/Http/Controllers/Api/V1/PayrollController.php',
            'app/Http/Controllers/Api/V1/StockMovementController.php',
            'app/Http/Controllers/Api/V1/TechnicianCashController.php',
        ];
    }

    public function test_focused_v1_controllers_do_not_return_raw_eloquent_payloads(): void
    {
        $controllerPaths = [
            'app/Http/Controllers/Api/V1/DepartmentController.php',
            'app/Http/Controllers/Api/V1/Billing/SaasPlanController.php',
            'app/Http/Controllers/Api/V1/Billing/SaasSubscriptionController.php',
            'app/Http/Controllers/Api/V1/EmailLogController.php',
            'app/Http/Controllers/Api/V1/FiscalInvoiceController.php',
            'app/Http/Controllers/Api/V1/Iam/RoleController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdConsentLogController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdDataRequestController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdDataTreatmentController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdDpoConfigController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdSecurityIncidentController.php',
            'app/Http/Controllers/Api/V1/StockMovementController.php',
        ];

        $rawReturnPattern = '/return\s+response\(\)->json\(\s*\$[A-Za-z_][A-Za-z0-9_]*(?:->|,|\))/';

        foreach ($controllerPaths as $controllerPath) {
            $source = file_get_contents(app_path(str_replace('app/', '', $controllerPath)));

            $this->assertIsString($source);
            $this->assertDoesNotMatchRegularExpression(
                $rawReturnPattern,
                $source,
                "{$controllerPath} ainda retorna model ou paginator cru em response()->json()."
            );
        }
    }

    public function test_corrected_v1_controllers_do_not_return_raw_eloquent_payloads(): void
    {
        $rawApiResponsePattern = '/ApiResponse::data\(\s*(?:'
            .'\$(?:dataset|dashboard|job|payroll|payslip|dept|department|pos|position|cashTx|tx|fundRequest)(?:->[A-Za-z_][A-Za-z0-9_]*\([^)]*\))*'
            .'|\$this->(?:findDataset|findDashboard)\([^)]*\)'
            .'|\$this->dataExportService->(?:retry|cancel)\([^)]*\)'
            .')\s*(?:,|\))/';

        foreach ($this->correctedControllerPaths() as $controllerPath) {
            $source = file_get_contents(app_path(str_replace('app/', '', $controllerPath)));

            $this->assertIsString($source);
            $this->assertDoesNotMatchRegularExpression(
                $rawApiResponsePattern,
                $source,
                "{$controllerPath} ainda retorna model Eloquent cru em ApiResponse::data()."
            );
        }
    }

    public function test_corrected_v1_controller_paginated_eloquent_results_use_explicit_resources(): void
    {
        $missingResourcePattern = '/ApiResponse::paginated\((?![^;]*Resource::class)[^;]*\);/s';

        foreach ($this->correctedControllerPaths() as $controllerPath) {
            $source = file_get_contents(app_path(str_replace('app/', '', $controllerPath)));

            $this->assertIsString($source);
            $this->assertDoesNotMatchRegularExpression(
                $missingResourcePattern,
                $source,
                "{$controllerPath} pagina Eloquent sem resourceClass explicito."
            );
        }
    }

    public function test_corrected_v1_controllers_use_api_response_instead_of_manual_json(): void
    {
        foreach ($this->correctedControllerPaths() as $controllerPath) {
            $source = file_get_contents(app_path(str_replace('app/', '', $controllerPath)));

            $this->assertIsString($source);
            $this->assertStringNotContainsString('response()->json(', $source, "{$controllerPath} ainda monta JSON manualmente.");
            $this->assertStringNotContainsString('->response()', $source, "{$controllerPath} deve retornar Resources via ApiResponse.");
        }
    }

    public function test_focused_v1_controllers_use_canonical_api_response_contract(): void
    {
        $controllerPaths = [
            'app/Http/Controllers/Api/V1/Billing/SaasPlanController.php',
            'app/Http/Controllers/Api/V1/Billing/SaasSubscriptionController.php',
            'app/Http/Controllers/Api/V1/Iam/RoleController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdDpoConfigController.php',
            'app/Http/Controllers/Api/V1/Lgpd/LgpdSecurityIncidentController.php',
        ];

        foreach ($controllerPaths as $controllerPath) {
            $source = file_get_contents(app_path(str_replace('app/', '', $controllerPath)));

            $this->assertIsString($source);
            $this->assertStringContainsString('ApiResponse::', $source, "{$controllerPath} deve usar App\\Support\\ApiResponse.");
            $this->assertStringNotContainsString('response()->json(', $source, "{$controllerPath} ainda monta JSON manualmente.");
            $this->assertStringNotContainsString('->response()', $source, "{$controllerPath} deve retornar via ApiResponse, nao via Resource::response().");
            $this->assertStringNotContainsString('->resolve()', $source, "{$controllerPath} nao deve resolver Resource manualmente.");
        }
    }

    public function test_work_order_auxiliary_routes_use_versioned_os_controllers(): void
    {
        $expectedControllers = [
            'api/v1/work-order-signatures' => 'App\\Http\\Controllers\\Api\\V1\\Os\\WorkOrderSignatureController@index',
            'api/v1/work-order-time-logs' => 'App\\Http\\Controllers\\Api\\V1\\Os\\WorkOrderTimeLogController@index',
            'api/v1/work-order-time-logs/start' => 'App\\Http\\Controllers\\Api\\V1\\Os\\WorkOrderTimeLogController@start',
        ];

        foreach ($expectedControllers as $uri => $expectedAction) {
            $route = $this->routeFor('GET', $uri) ?? $this->routeFor('POST', $uri);

            $this->assertNotNull($route, "Rota {$uri} nao encontrada.");
            $this->assertSame($expectedAction, ltrim($route->getActionName(), '\\'));
        }
    }

    public function test_public_api_routes_only_exist_in_v1_namespace(): void
    {
        $routes = [
            ['POST', 'api/rate/{token}', 'api/v1/rate/{token}'],
            ['POST', 'api/webhooks/whatsapp', 'api/v1/webhooks/whatsapp'],
            ['POST', 'api/webhooks/email', 'api/v1/webhooks/email'],
            ['GET', 'api/quotes/{quote}/public-view', 'api/v1/quotes/{quote}/public-view'],
            ['GET', 'api/quotes/{quote}/public-pdf', 'api/v1/quotes/{quote}/public-pdf'],
            ['POST', 'api/quotes/{quote}/public-approve', 'api/v1/quotes/{quote}/public-approve'],
            ['GET', 'api/quotes/proposal/{magicToken}', 'api/v1/quotes/proposal/{magicToken}'],
            ['POST', 'api/quotes/proposal/{magicToken}/approve', 'api/v1/quotes/proposal/{magicToken}/approve'],
            ['POST', 'api/quotes/proposal/{magicToken}/reject', 'api/v1/quotes/proposal/{magicToken}/reject'],
            ['GET', 'api/track/os/{workOrder}', 'api/v1/track/os/{workOrder}'],
            ['GET', 'api/pixel/{trackingId}', 'api/v1/pixel/{trackingId}'],
        ];

        foreach ($routes as [$method, $legacyUri, $v1Uri]) {
            $legacyRoute = $this->routeFor($method, $legacyUri);
            $v1Route = $this->routeFor($method, $v1Uri);

            $this->assertNotNull($v1Route, "Alias canonico {$method} {$v1Uri} nao encontrado.");
            $this->assertNull(
                $legacyRoute,
                "Alias publico legado {$method} {$legacyUri} mantem contrato paralelo fora de /api/v1."
            );
        }
    }

    private function routeFor(string $method, string $uri): ?LaravelRoute
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() !== $uri) {
                continue;
            }

            if (! in_array($method, $route->methods(), true)) {
                continue;
            }

            return $route;
        }

        return null;
    }
}
