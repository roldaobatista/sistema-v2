<?php

namespace Tests\Feature\Api\V1;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackendApiHarnessContractsTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    public static function controllersPassingInputToServices(): array
    {
        return [
            'crm controller' => ['app/Http/Controllers/Api/V1/CrmController.php'],
            'inmetro controller' => ['app/Http/Controllers/Api/V1/InmetroController.php'],
            'hr advanced controller' => ['app/Http/Controllers/Api/V1/HRAdvancedController.php'],
            'tech sync controller' => ['app/Http/Controllers/Api/V1/TechSyncController.php'],
            'work order controller' => ['app/Http/Controllers/Api/V1/Os/WorkOrderController.php'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function controllersRequiringApiResponseEnvelope(): array
    {
        return [
            'ai assistant controller' => ['app/Http/Controllers/Api/V1/Analytics/AiAssistantController.php'],
            'cash flow controller' => ['app/Http/Controllers/Api/V1/CashFlowController.php'],
            'hr advanced controller' => ['app/Http/Controllers/Api/V1/HRAdvancedController.php'],
            'tenant controller' => ['app/Http/Controllers/Api/V1/TenantController.php'],
        ];
    }

    #[DataProvider('controllersPassingInputToServices')]
    public function test_controller_does_not_pass_raw_request_payload_to_services(string $controllerPath): void
    {
        $source = file_get_contents(base_path($controllerPath));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('$request->all()', $source);
        $this->assertStringNotContainsString('validated() : $request->all()', $source);
    }

    #[DataProvider('controllersRequiringApiResponseEnvelope')]
    public function test_controller_uses_api_response_envelope_instead_of_raw_json(string $controllerPath): void
    {
        $source = file_get_contents(base_path($controllerPath));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('response()->json(', $source);
    }
}
