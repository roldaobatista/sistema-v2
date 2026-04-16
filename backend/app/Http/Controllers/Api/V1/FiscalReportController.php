<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\ExportAccountantRequest;
use App\Http\Requests\Fiscal\LedgerReportRequest;
use App\Http\Requests\Fiscal\SpedFiscalReportRequest;
use App\Services\Fiscal\FiscalReportService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FiscalReportController extends Controller
{
    public function __construct(private FiscalReportService $reportService) {}

    /**
     * #1 — SPED Fiscal report.
     */
    public function spedFiscal(SpedFiscalReportRequest $request): JsonResponse
    {
        try {
            $v = $request->validated();
            $result = $this->reportService->generateSpedFiscal(
                $request->user()->tenant,
                Carbon::parse($v['inicio']),
                Carbon::parse($v['fim']),
            );

            return ApiResponse::data($result);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar SPED Fiscal', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório SPED Fiscal.', 500);
        }
    }

    /**
     * #2 — Tax dashboard.
     */
    public function taxDashboard(Request $request): JsonResponse
    {
        try {
            $periodo = $request->query('periodo', 'month');
            $result = $this->reportService->taxDashboard($request->user()->tenant, $periodo);

            return ApiResponse::data($result);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar dashboard fiscal', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar dashboard fiscal.', 500);
        }
    }

    /**
     * #3 — Export ZIP for accountant.
     */
    public function exportAccountant(ExportAccountantRequest $request): BinaryFileResponse|JsonResponse
    {
        try {
            $mes = $request->validated()['mes'];
            $result = $this->reportService->exportForAccountant(
                $request->user()->tenant,
                Carbon::parse($mes.'-01'),
            );

            if (! $result['success']) {
                return ApiResponse::message($result['error'] ?? 'Erro ao exportar', 404);
            }

            return response()->download($result['full_path'], $result['file_name'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Erro ao exportar para contador', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar arquivos para o contador.', 500);
        }
    }

    /**
     * #4 — Ledger report.
     */
    public function ledger(LedgerReportRequest $request): JsonResponse
    {
        try {
            $v = $request->validated();
            $result = $this->reportService->ledgerReport(
                $request->user()->tenant,
                Carbon::parse($v['inicio']),
                Carbon::parse($v['fim']),
            );

            return ApiResponse::data($result);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar livro razão', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de livro razão.', 500);
        }
    }

    /**
     * #5 — Tax forecast.
     */
    public function taxForecast(Request $request): JsonResponse
    {
        try {
            $result = $this->reportService->taxForecast($request->user()->tenant);

            return ApiResponse::data($result);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar previsão fiscal', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar previsão fiscal.', 500);
        }
    }
}
