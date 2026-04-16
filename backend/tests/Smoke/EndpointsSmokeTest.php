<?php

namespace Tests\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Smoke: Endpoints de listagem (GET) dos módulos core
 * Verifica que cada módulo crítico responde 200 ao listar.
 *
 * Paths baseados nas rotas reais em routes/api/*.php
 */
class EndpointsSmokeTest extends SmokeTestCase
{
    #[DataProvider('criticalEndpoints')]
    public function test_endpoint_returns_ok(string $endpoint): void
    {
        $response = $this->getJson($endpoint);
        $response->assertOk();
    }

    public static function criticalEndpoints(): array
    {
        return [
            // master.php
            'customers' => ['/api/v1/customers'],
            'suppliers' => ['/api/v1/suppliers'],
            'products' => ['/api/v1/products'],

            // quotes-service-calls.php
            'quotes' => ['/api/v1/quotes'],
            'service-calls' => ['/api/v1/service-calls'],

            // work-orders.php
            'work-orders' => ['/api/v1/work-orders'],

            // stock.php
            'stock-movements' => ['/api/v1/stock/movements'],
            'stock-summary' => ['/api/v1/stock/summary'],

            // equipment-platform.php
            'equipments' => ['/api/v1/equipments'],

            // financial.php
            'accounts-receivable-summary' => ['/api/v1/accounts-receivable-summary'],
            'accounts-payable-summary' => ['/api/v1/accounts-payable-summary'],

            // dashboard_iam.php
            'users' => ['/api/v1/users'],
        ];
    }
}
