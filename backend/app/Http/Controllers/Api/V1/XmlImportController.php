<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\XmlImportRequest;
use App\Services\XmlImportService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class XmlImportController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(protected XmlImportService $xmlImportService) {}

    public function import(XmlImportRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        try {
            $xmlContent = file_get_contents($request->file('xml_file')->getRealPath());
            $result = $this->xmlImportService->processNfe($xmlContent, $request->warehouse_id);

            return ApiResponse::data($result, 200, ['message' => 'Processamento de XML concluído']);
        } catch (\Exception $e) {
            Log::error('XML Import failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'warehouse_id' => $request->warehouse_id,
            ]);

            return ApiResponse::message('Erro ao processar o arquivo XML: '.mb_substr($e->getMessage(), 0, 200), 422);
        }
    }
}
