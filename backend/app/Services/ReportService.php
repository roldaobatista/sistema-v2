<?php

namespace App\Services;

use App\Actions\Report\GenerateCommissionsReportAction;
use App\Actions\Report\GenerateCrmReportAction;
use App\Actions\Report\GenerateCustomersReportAction;
use App\Actions\Report\GenerateEquipmentsReportAction;
use App\Actions\Report\GenerateExpensesReportAction;
use App\Actions\Report\GenerateFinancialReportAction;
use App\Actions\Report\GenerateProductivityReportAction;
use App\Actions\Report\GenerateProfitabilityReportAction;
use App\Actions\Report\GenerateQuotesReportAction;
use App\Actions\Report\GenerateServiceCallsReportAction;
use App\Actions\Report\GenerateStockReportAction;
use App\Actions\Report\GenerateSuppliersReportAction;
use App\Actions\Report\GenerateTechnicianCashReportAction;
use App\Actions\Report\GenerateWorkOrdersReportAction;

class ReportService
{
    public function getWorkOrders(int $tenantId, array $filters): array
    {
        return app(GenerateWorkOrdersReportAction::class)->execute($tenantId, $filters);
    }

    public function getProductivity(int $tenantId, array $filters): array
    {
        return app(GenerateProductivityReportAction::class)->execute($tenantId, $filters);
    }

    public function getFinancial(int $tenantId, array $filters): array
    {
        return app(GenerateFinancialReportAction::class)->execute($tenantId, $filters);
    }

    public function getExpenses(int $tenantId, array $filters): array
    {
        return app(GenerateExpensesReportAction::class)->execute($tenantId, $filters);
    }

    public function getCommissions(int $tenantId, array $filters): array
    {
        return app(GenerateCommissionsReportAction::class)->execute($tenantId, $filters);
    }

    public function getProfitability(int $tenantId, array $filters): array
    {
        return app(GenerateProfitabilityReportAction::class)->execute($tenantId, $filters);
    }

    public function getQuotes(int $tenantId, array $filters): array
    {
        return app(GenerateQuotesReportAction::class)->execute($tenantId, $filters);
    }

    public function getServiceCalls(int $tenantId, array $filters): array
    {
        return app(GenerateServiceCallsReportAction::class)->execute($tenantId, $filters);
    }

    public function getTechnicianCash(int $tenantId, array $filters): array
    {
        return app(GenerateTechnicianCashReportAction::class)->execute($tenantId, $filters);
    }

    public function getCrm(int $tenantId, array $filters): array
    {
        return app(GenerateCrmReportAction::class)->execute($tenantId, $filters);
    }

    public function getEquipments(int $tenantId, array $filters): array
    {
        return app(GenerateEquipmentsReportAction::class)->execute($tenantId, $filters);
    }

    public function getSuppliers(int $tenantId, array $filters): array
    {
        return app(GenerateSuppliersReportAction::class)->execute($tenantId, $filters);
    }

    public function getStock(int $tenantId, array $filters): array
    {
        return app(GenerateStockReportAction::class)->execute($tenantId, $filters);
    }

    public function getCustomers(int $tenantId, array $filters): array
    {
        return app(GenerateCustomersReportAction::class)->execute($tenantId, $filters);
    }
}
