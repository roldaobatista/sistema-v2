<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CommissionsReportRequest;
use App\Http\Requests\Report\CrmReportRequest;
use App\Http\Requests\Report\CustomersReportRequest;
use App\Http\Requests\Report\EquipmentsReportRequest;
use App\Http\Requests\Report\ExpensesReportRequest;
use App\Http\Requests\Report\FinancialReportRequest;
use App\Http\Requests\Report\ProductivityReportRequest;
use App\Http\Requests\Report\ProfitabilityReportRequest;
use App\Http\Requests\Report\QuotesReportRequest;
use App\Http\Requests\Report\ReportExportRequest;
use App\Http\Requests\Report\ServiceCallsReportRequest;
use App\Http\Requests\Report\StockReportRequest;
use App\Http\Requests\Report\SuppliersReportRequest;
use App\Http\Requests\Report\TechnicianCashReportRequest;
use App\Http\Requests\Report\WorkOrdersReportRequest;
use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private ReportService $service,
        private ReportExportService $exportService
    ) {}

    public function workOrders(WorkOrdersReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getWorkOrders($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report workOrders failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de ordens de serviço.', 500);
        }
    }

    public function productivity(ProductivityReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getProductivity($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report productivity failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de produtividade.', 500);
        }
    }

    public function financial(FinancialReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getFinancial($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report financial failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório financeiro.', 500);
        }
    }

    public function expenses(ExpensesReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getExpenses($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report expenses failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de despesas.', 500);
        }
    }

    public function commissions(CommissionsReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getCommissions($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report commissions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de comissões.', 500);
        }
    }

    public function profitability(ProfitabilityReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getProfitability($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report profitability failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de lucratividade.', 500);
        }
    }

    public function quotes(QuotesReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getQuotes($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report quotes failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de orçamentos.', 500);
        }
    }

    public function serviceCalls(ServiceCallsReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getServiceCalls($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report serviceCalls failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de chamados.', 500);
        }
    }

    public function technicianCash(TechnicianCashReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getTechnicianCash($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report technicianCash failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de caixa do técnico.', 500);
        }
    }

    public function crm(CrmReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getCrm($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report CRM failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de CRM.', 500);
        }
    }

    public function equipments(EquipmentsReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getEquipments($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report equipments failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de equipamentos.', 500);
        }
    }

    public function suppliers(SuppliersReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getSuppliers($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report suppliers failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de fornecedores.', 500);
        }
    }

    public function stock(StockReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getStock($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report stock failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de estoque.', 500);
        }
    }

    public function customers(CustomersReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getCustomers($this->resolvedTenantId(), $request->validated());

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Report customers failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de clientes.', 500);
        }
    }

    public function export(ReportExportRequest $request, string $type)
    {
        $allowedTypes = [
            'work-orders', 'productivity', 'financial', 'commissions',
            'profitability', 'quotes', 'service-calls', 'technician-cash',
            'crm', 'equipments', 'suppliers', 'stock', 'customers',
        ];
        if (! in_array($type, $allowedTypes, true)) {
            return ApiResponse::message('Tipo de relatório inválido para exportação.', 422, ['allowed_types' => $allowedTypes]);
        }

        try {
            $tenantId = $this->resolvedTenantId();
            $from = $request->get('from');
            $to = $request->get('to');
            $branchId = $request->filled('branch_id') ? (int) $request->get('branch_id') : null;

            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=relatorio-{$type}-{$from}-{$to}.csv",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            $callback = function () use ($type, $tenantId, $from, $to, $branchId) {
                $file = fopen('php://output', 'w');
                if (! is_resource($file)) {
                    throw new \RuntimeException('Failed to open output stream');
                }
                $this->exportService->streamCsvExport($type, $tenantId, $from, $to, $branchId, $file);
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Throwable $e) {
            Log::error('Report export failed', ['type' => $type, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar relatório.', 500);
        }
    }
}
