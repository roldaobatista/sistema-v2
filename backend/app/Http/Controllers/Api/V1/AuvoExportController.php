<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Services\Auvo\AuvoApiClient;
use App\Services\Auvo\AuvoExportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuvoExportController extends Controller
{
    private const EXPORT_FAILURE_MESSAGE = 'Falha ao exportar %s para o Auvo. Tente novamente em instantes.';

    private function getExportService(Request $request): AuvoExportService
    {
        $client = AuvoApiClient::forTenant($request->user()->current_tenant_id);

        if (! $client->hasCredentials()) {
            throw new \RuntimeException('Credenciais Auvo não configuradas. Configure em Integração > Auvo.');
        }

        return new AuvoExportService($client);
    }

    public function exportCustomer(Request $request, Customer $customer): JsonResponse
    {
        return $this->handleExport($request, 'Cliente', function (AuvoExportService $svc) use ($customer) {
            return $svc->exportCustomer($customer);
        });
    }

    public function exportProduct(Request $request, Product $product): JsonResponse
    {
        return $this->handleExport($request, 'Produto', function (AuvoExportService $svc) use ($product) {
            return $svc->exportProduct($product);
        });
    }

    public function exportServiceEntity(Request $request, Service $service): JsonResponse
    {
        return $this->handleExport($request, 'Serviço', function (AuvoExportService $svc) use ($service) {
            return $svc->exportService($service);
        });
    }

    public function exportQuote(Request $request, Quote $quote): JsonResponse
    {
        return $this->handleExport($request, 'Orçamento', function (AuvoExportService $svc) use ($quote) {
            return $svc->exportQuote($quote);
        });
    }

    private function handleExport(Request $request, string $entityLabel, callable $action): JsonResponse
    {
        try {
            $svc = $this->getExportService($request);
            $result = $action($svc);

            return ApiResponse::data($result, 200, ['message' => "{$entityLabel} exportado(a) com sucesso para o Auvo."]);
        } catch (\RuntimeException $e) {
            $isCredentialError = str_contains($e->getMessage(), 'Credenciais');
            $status = $isCredentialError ? 422 : 500;

            Log::error("Auvo export {$entityLabel} failed", ['error' => $e->getMessage()]);

            return ApiResponse::message(
                $isCredentialError
                    ? $e->getMessage()
                    : $this->genericExportFailureMessage($entityLabel),
                $status
            );
        } catch (\Throwable $e) {
            Log::error("Auvo export {$entityLabel} failed", [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message($this->genericExportFailureMessage($entityLabel), 500);
        }
    }

    private function genericExportFailureMessage(string $entityLabel): string
    {
        return sprintf(self::EXPORT_FAILURE_MESSAGE, $entityLabel);
    }
}
