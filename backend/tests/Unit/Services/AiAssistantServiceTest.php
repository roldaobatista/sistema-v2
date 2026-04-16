<?php

namespace Tests\Unit\Services;

use App\Services\AiAssistantService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiAssistantServiceTest extends TestCase
{
    private AiAssistantService $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->assistant = app(AiAssistantService::class);
    }

    public function test_list_tools_returns_all_tools(): void
    {
        $tools = $this->assistant->listTools();

        $this->assertIsArray($tools);
        $this->assertArrayHasKey('predictive_maintenance', $tools);
        $this->assertArrayHasKey('financial_anomalies', $tools);
        $this->assertArrayHasKey('integration_health', $tools);
        $this->assertArrayHasKey('churn_prediction', $tools);
        $this->assertArrayHasKey('natural_language_report', $tools);
        $this->assertCount(13, $tools);
    }

    public function test_each_tool_has_description(): void
    {
        foreach ($this->assistant->listTools() as $name => $tool) {
            $this->assertArrayHasKey('description', $tool, "Tool '{$name}' has no description");
            $this->assertNotEmpty($tool['description'], "Tool '{$name}' has empty description");
        }
    }

    public function test_chat_returns_expected_structure(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Como está o sistema?', $tenantId);

        $this->assertArrayHasKey('answer', $response);
        $this->assertArrayHasKey('tools_used', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsString($response['answer']);
        $this->assertIsArray($response['tools_used']);
    }

    public function test_chat_selects_maintenance_tool_for_calibration_question(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Quais equipamentos precisam de calibração?', $tenantId);

        $this->assertContains('predictive_maintenance', $response['tools_used']);
    }

    public function test_chat_selects_financial_tool_for_anomaly_question(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Existe alguma anomalia financeira no sistema?', $tenantId);

        $this->assertContains('financial_anomalies', $response['tools_used']);
    }

    public function test_chat_selects_integration_health_tool(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Como está a saúde das integrações?', $tenantId);

        $this->assertContains('integration_health', $response['tools_used']);
    }

    public function test_chat_selects_churn_tool(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Quais clientes têm risco de churn?', $tenantId);

        $this->assertContains('churn_prediction', $response['tools_used']);
    }

    public function test_chat_falls_back_to_report_for_generic_question(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Olá, como vai?', $tenantId);

        $this->assertContains('natural_language_report', $response['tools_used']);
    }

    public function test_chat_selects_multiple_tools_for_complex_question(): void
    {
        $tenantId = 1;
        app()->instance('current_tenant_id', $tenantId);

        $response = $this->assistant->chat('Mostre anomalias financeiras e risco de churn dos clientes', $tenantId);

        $this->assertContains('financial_anomalies', $response['tools_used']);
        $this->assertContains('churn_prediction', $response['tools_used']);
    }
}
