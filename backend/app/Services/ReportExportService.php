<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\Supplier;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportExportService
{
    private function validatedDate(?string $value, string $default): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @param  resource  $fileStream
     */
    public function streamCsvExport(string $type, int $tenantId, ?string $fromRaw, ?string $toRaw, ?int $branchId, $fileStream): void
    {
        $from = $this->validatedDate($fromRaw, now()->startOfMonth()->toDateString());
        $to = $this->validatedDate($toRaw, now()->toDateString());

        fwrite($fileStream, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM

        match ($type) {
            'work-orders' => $this->exportWorkOrders($tenantId, $from, $to, $branchId, $fileStream),
            'productivity' => $this->exportProductivity($tenantId, $from, $to, $branchId, $fileStream),
            'financial' => $this->exportFinancial($tenantId, $from, $to, $branchId, $fileStream),
            'commissions' => $this->exportCommissions($tenantId, $from, $to, $branchId, $fileStream),
            'profitability' => $this->exportProfitability($tenantId, $from, $to, $branchId, $fileStream),
            'quotes' => $this->exportQuotes($tenantId, $from, $to, $branchId, $fileStream),
            'service-calls' => $this->exportServiceCalls($tenantId, $from, $to, $branchId, $fileStream),
            'technician-cash' => $this->exportTechnicianCash($tenantId, $from, $to, $branchId, $fileStream),
            'crm' => $this->exportCrm($tenantId, $from, $to, $branchId, $fileStream),
            'equipments' => $this->exportEquipments($tenantId, $from, $to, $branchId, $fileStream),
            'suppliers' => $this->exportSuppliers($tenantId, $from, $to, $branchId, $fileStream),
            'stock' => $this->exportStock($tenantId, $from, $to, $fileStream),
            'customers' => $this->exportCustomers($tenantId, $from, $to, $branchId, $fileStream),
            default => null,
        };
    }

    /**
     * @param  resource  $file
     */
    private function exportWorkOrders(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Ordens de Servico'], ';');
        fputcsv($file, ['ID', 'Numero OS', 'Status', 'Prioridade', 'Total', 'Criado em', 'Concluido em'], ';');
        WorkOrder::where('tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->os_number ?? $item->number,
                        $item->status instanceof \BackedEnum ? $item->status->value : $item->status,
                        $item->priority,
                        $item->total,
                        $item->created_at ? Carbon::parse($item->created_at)->format('d/m/Y H:i') : '',
                        $item->completed_at ? Carbon::parse($item->completed_at)->format('d/m/Y H:i') : '',
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportProductivity(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Produtividade'], ';');
        fputcsv($file, ['ID Tecnico', 'Tecnico', 'Minutos Trabalho', 'Minutos Deslocamento', 'Minutos Espera'], ';');
        DB::table('time_entries')
            ->join('users', 'users.id', '=', 'time_entries.technician_id')
            ->whereBetween('time_entries.started_at', [$from, "{$to} 23:59:59"])
            ->where('time_entries.tenant_id', $tenantId)
            ->whereNull('time_entries.deleted_at')
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->select(
                'users.id',
                'users.name',
                DB::raw("SUM(CASE WHEN type = 'work' THEN duration_minutes ELSE 0 END) as work_minutes"),
                DB::raw("SUM(CASE WHEN type = 'travel' THEN duration_minutes ELSE 0 END) as travel_minutes"),
                DB::raw("SUM(CASE WHEN type = 'waiting' THEN duration_minutes ELSE 0 END) as waiting_minutes")
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.name')
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->name,
                        $item->work_minutes,
                        $item->travel_minutes,
                        $item->waiting_minutes,
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportFinancial(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Financeiro'], ';');
        fputcsv($file, ['Tipo', 'Categoria/Fornecedor/Cliente', 'Descricao', 'Valor', 'Status', 'Data'], ';');
        $arStatsQuery = AccountReceivable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, "{$to} 23:59:59"])
            ->with('customer:id,name');
        if ($branchId) {
            $arStatsQuery->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId));
        }
        $arStatsQuery->chunk(500, function ($items) use ($file) {
            foreach ($items as $item) {
                fputcsv($file, [
                    'Receita',
                    $item->customer->name ?? '-',
                    $item->description,
                    $item->amount,
                    is_object($item->status) && enum_exists(get_class($item->status)) ? $item->status->value : $item->status,
                    Carbon::parse($item->due_date)->format('d/m/Y'),
                ], ';');
            }
        });

        $apStatsQuery = AccountPayable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, "{$to} 23:59:59"])
            ->with('supplierRelation:id,name');

        $apStatsQuery->chunk(500, function ($items) use ($file) {
            foreach ($items as $item) {
                fputcsv($file, [
                    'Despesa (AP)',
                    $item->supplierRelation->name ?? '-',
                    $item->description,
                    $item->amount,
                    is_object($item->status) && enum_exists(get_class($item->status)) ? $item->status->value : $item->status,
                    Carbon::parse($item->due_date)->format('d/m/Y'),
                ], ';');
            }
        });
    }

    /**
     * @param  resource  $file
     */
    private function exportCommissions(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Comissoes'], ';');
        fputcsv($file, ['Tecnico', 'Qtd Eventos', 'Comissao Pendente', 'Comissao Paga', 'Total Comissao'], ';');
        $byTechQuery = CommissionEvent::join('users', 'users.id', '=', 'commission_events.user_id')
            ->where('commission_events.tenant_id', $tenantId)
            ->whereBetween('commission_events.created_at', [$from, "{$to} 23:59:59"]);
        if ($branchId) {
            $byTechQuery->join('work_orders as wo_br', 'wo_br.id', '=', 'commission_events.work_order_id')
                ->where('wo_br.branch_id', $branchId);
        }
        $byTechQuery->select(
            'users.id',
            'users.name',
            DB::raw('COUNT(*) as events_count'),
            DB::raw("SUM(CASE WHEN commission_events.status = '".CommissionEvent::STATUS_PENDING."' THEN commission_amount ELSE 0 END) as pending"),
            DB::raw("SUM(CASE WHEN commission_events.status = '".CommissionEvent::STATUS_PAID."' THEN commission_amount ELSE 0 END) as paid"),
            DB::raw('SUM(commission_amount) as total_commission')
        )
            ->groupBy('users.id', 'users.name')
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->name,
                        $item->events_count,
                        $item->pending,
                        $item->paid,
                        $item->total_commission,
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportProfitability(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Lucratividade'], ';');
        fputcsv($file, ['Metrica', 'Valor (R$)'], ';');

        $revenuePaymentsQuery = Payment::query()
            ->join('accounts_receivable as ar_profit', 'payments.payable_id', '=', 'ar_profit.id')
            ->leftJoin('work_orders as wo_profit', 'ar_profit.work_order_id', '=', 'wo_profit.id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.payable_type', AccountReceivable::class)
            ->where('ar_profit.tenant_id', $tenantId)
            ->where('ar_profit.status', '!=', AccountReceivable::STATUS_CANCELLED)
            ->whereBetween('payments.payment_date', [$from, "{$to} 23:59:59"]);
        if ($branchId) {
            $revenuePaymentsQuery->where(function ($q) use ($branchId) {
                $q->where('wo_profit.branch_id', $branchId)->orWhereNull('ar_profit.work_order_id');
            });
        }
        $revenuePayments = (string) ($revenuePaymentsQuery->sum('payments.amount'));

        $legacyRevenueQuery = DB::table('accounts_receivable as ar_legacy_profit')
            ->leftJoin('work_orders as wo_legacy_profit', 'ar_legacy_profit.work_order_id', '=', 'wo_legacy_profit.id')
            ->where('ar_legacy_profit.tenant_id', $tenantId)
            ->whereNull('ar_legacy_profit.deleted_at')
            ->where('ar_legacy_profit.status', '!=', AccountReceivable::STATUS_CANCELLED)
            ->where('ar_legacy_profit.amount_paid', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)->from('payments')->whereColumn('payments.payable_id', 'ar_legacy_profit.id')->where('payments.payable_type', AccountReceivable::class);
            })
            ->whereBetween(DB::raw('COALESCE(ar_legacy_profit.paid_at, ar_legacy_profit.due_date)'), [$from, "{$to} 23:59:59"]);
        if ($branchId) {
            $legacyRevenueQuery->where(function ($q) use ($branchId) {
                $q->where('wo_legacy_profit.branch_id', $branchId)->orWhereNull('ar_legacy_profit.work_order_id');
            });
        }
        $legacyRevenue = (string) ($legacyRevenueQuery->sum('ar_legacy_profit.amount_paid'));
        $revenue = bcadd($revenuePayments, $legacyRevenue, 2);

        $costPaymentsQuery = Payment::query()
            ->join('accounts_payable as ap_profit', 'payments.payable_id', '=', 'ap_profit.id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.payable_type', AccountPayable::class)
            ->where('ap_profit.tenant_id', $tenantId)
            ->where('ap_profit.status', '!=', AccountPayable::STATUS_CANCELLED)
            ->whereBetween('payments.payment_date', [$from, "{$to} 23:59:59"]);
        $costPayments = (string) ($costPaymentsQuery->sum('payments.amount'));

        $legacyCostsQuery = DB::table('accounts_payable as ap_legacy_profit')
            ->where('ap_legacy_profit.tenant_id', $tenantId)
            ->whereNull('ap_legacy_profit.deleted_at')
            ->where('ap_legacy_profit.status', '!=', AccountPayable::STATUS_CANCELLED)
            ->where('ap_legacy_profit.amount_paid', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)->from('payments')->whereColumn('payments.payable_id', 'ap_legacy_profit.id')->where('payments.payable_type', AccountPayable::class);
            })
            ->whereBetween(DB::raw('COALESCE(ap_legacy_profit.paid_at, ap_legacy_profit.due_date)'), [$from, "{$to} 23:59:59"]);
        $legacyCosts = (string) ($legacyCostsQuery->sum('ap_legacy_profit.amount_paid'));
        $costs = bcadd($costPayments, $legacyCosts, 2);

        $expensesQuery = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from, "{$to} 23:59:59"])
            ->whereIn('status', [ExpenseStatus::APPROVED]);
        if ($branchId) {
            $expensesQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))->orWhereNull('work_order_id');
            });
        }
        $expenses = (string) ($expensesQuery->sum('amount'));

        $commissionsQuery = CommissionEvent::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->whereIn('status', [CommissionEvent::STATUS_APPROVED, CommissionEvent::STATUS_PAID]);
        if ($branchId) {
            $commissionsQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))->orWhereNull('work_order_id');
            });
        }
        $commissions = (string) ($commissionsQuery->sum('commission_amount'));

        $itemCostsQuery = DB::table('work_order_items')
            ->join('work_orders', 'work_order_items.work_order_id', '=', 'work_orders.id')
            ->where('work_order_items.type', WorkOrderItem::TYPE_PRODUCT)
            ->whereNotNull('work_order_items.cost_price')
            ->where('work_orders.tenant_id', $tenantId)
            ->whereBetween('work_orders.completed_at', [$from, "{$to} 23:59:59"]);
        if ($branchId) {
            $itemCostsQuery->where('work_orders.branch_id', $branchId);
        }
        $itemCosts = (string) ($itemCostsQuery->selectRaw('SUM(work_order_items.cost_price * work_order_items.quantity) as total')->value('total') ?? 0);

        $totalCosts = bcadd((string) bcadd((string) bcadd($costs, $expenses, 2), $commissions, 2), $itemCosts, 2);
        $profit = bcsub($revenue, $totalCosts, 2);
        $margin = bccomp($revenue, '0', 2) > 0 ? round((float) bcdiv(bcmul($profit, '100', 4), $revenue, 4), 1) : 0;

        fputcsv($file, ['Receitas (+)', number_format((float) $revenue, 2, ',', '')], ';');
        fputcsv($file, ['Custos Diretos (AP) (-)', number_format((float) $costs, 2, ',', '')], ';');
        fputcsv($file, ['Custo de Pecas (OS) (-)', number_format((float) $itemCosts, 2, ',', '')], ';');
        fputcsv($file, ['Despesas (-)', number_format((float) $expenses, 2, ',', '')], ';');
        fputcsv($file, ['Comissoes (-)', number_format((float) $commissions, 2, ',', '')], ';');
        fputcsv($file, ['Total Custos', number_format((float) $totalCosts, 2, ',', '')], ';');
        fputcsv($file, ['Lucro Liquido', number_format((float) $profit, 2, ',', '')], ';');
        fputcsv($file, ['Margem de Lucro (%)', number_format((float) $margin, 1, ',', '').'%'], ';');
    }

    /**
     * @param  resource  $file
     */
    private function exportQuotes(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Orcamentos'], ';');
        fputcsv($file, ['ID', 'Vendedor', 'Status', 'Total', 'Criado em'], ';');
        Quote::where('quotes.tenant_id', $tenantId)
            ->join('users', 'users.id', '=', 'quotes.seller_id')
            ->whereBetween('quotes.created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->select('quotes.id', 'users.name as seller_name', 'quotes.status', 'quotes.total', 'quotes.created_at')
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->seller_name,
                        $item->status,
                        $item->total,
                        Carbon::parse($item->created_at)->format('d/m/Y H:i'),
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportServiceCalls(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Chamados'], ';');
        fputcsv($file, ['ID', 'Tecnico', 'Status', 'Prioridade', 'Criado em'], ';');
        ServiceCall::where('service_calls.tenant_id', $tenantId)
            ->leftJoin('users', 'users.id', '=', 'service_calls.technician_id')
            ->whereBetween('service_calls.created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->select('service_calls.id', 'users.name as tech_name', 'service_calls.status', 'service_calls.priority', 'service_calls.created_at')
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->tech_name ?? 'Sem tecnico',
                        $item->status,
                        $item->priority,
                        Carbon::parse($item->created_at)->format('d/m/Y H:i'),
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportTechnicianCash(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Caixa do Tecnico'], ';');
        fputcsv($file, ['Tecnico', 'Saldo Atual', 'Creditos Periodo', 'Debitos Periodo'], ';');
        $fundsQuery = TechnicianCashFund::where('tenant_id', $tenantId)->with('technician:id,name,branch_id');
        if ($branchId) {
            $fundsQuery->whereHas('technician', fn ($q) => $q->where('branch_id', $branchId));
        }
        $fundsQuery->chunk(500, function ($items) use ($file, $tenantId, $from, $to) {
            /** @var TechnicianCashFund $fund */
            foreach ($items as $fund) {
                $transactions = $fund->transactions()
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('transaction_date', [$from, "{$to} 23:59:59"]);

                $credits = (clone $transactions)->where('type', TechnicianCashTransaction::TYPE_CREDIT)->sum('amount');
                $debits = (clone $transactions)->where('type', TechnicianCashTransaction::TYPE_DEBIT)->sum('amount');

                fputcsv($file, [
                    $fund->technician->name ?? '-',
                    $fund->balance,
                    $credits,
                    $debits,
                ], ';');
            }
        });
    }

    /**
     * @param  resource  $file
     */
    private function exportCrm(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'CRM'], ';');
        fputcsv($file, ['ID', 'Negocio', 'Vendedor', 'Valor', 'Status', 'Ganho Em', 'Criado Em'], ';');
        CrmDeal::where('crm_deals.tenant_id', $tenantId)
            ->leftJoin('users', 'users.id', '=', 'crm_deals.assigned_to')
            ->whereBetween('crm_deals.created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->select('crm_deals.id', 'crm_deals.title', 'users.name as seller_name', 'crm_deals.value', 'crm_deals.status', 'crm_deals.won_at', 'crm_deals.created_at')
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->title,
                        $item->seller_name,
                        $item->value,
                        $item->status,
                        $item->won_at ? Carbon::parse($item->won_at)->format('d/m/Y H:i') : '',
                        Carbon::parse($item->created_at)->format('d/m/Y H:i'),
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportEquipments(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Equipamentos'], ';');
        fputcsv($file, ['ID', 'Codigo', 'Cliente', 'Marca', 'Modelo', 'N Serie', 'Status', 'Proxima Calibracao'], ';');
        $query = Equipment::where('tenant_id', $tenantId)->with('customer:id,name');
        if ($branchId) {
            $query->whereHas('responsible', fn ($q) => $q->where('branch_id', $branchId));
        }
        $query->chunk(500, function ($items) use ($file) {
            foreach ($items as $item) {
                fputcsv($file, [
                    $item->id,
                    $item->code,
                    $item->customer->name ?? '-',
                    $item->brand,
                    $item->model,
                    $item->serial_number,
                    $item->status,
                    $item->next_calibration_at ? Carbon::parse($item->next_calibration_at)->format('d/m/Y') : '',
                ], ';');
            }
        });
    }

    /**
     * @param  resource  $file
     */
    private function exportSuppliers(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Fornecedores'], ';');
        fputcsv($file, ['ID', 'Nome', 'Documento', 'Tipo', 'Telefone', 'Email', 'Status'], ';');
        Supplier::where('tenant_id', $tenantId)
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->name,
                        $item->document,
                        $item->type,
                        $item->phone,
                        $item->email,
                        $item->is_active ? 'Ativo' : 'Inativo',
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportStock(int $tenantId, string $from, string $to, $file): void
    {
        fputcsv($file, ['section', 'Estoque'], ';');
        fputcsv($file, ['ID', 'Codigo', 'Nome', 'Quantidade', 'Custo Medio', 'Preco Venda', 'Valor Total', 'Status'], ';');
        Product::where('tenant_id', $tenantId)
            ->chunk(500, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->id,
                        $item->code,
                        $item->name,
                        number_format($item->stock_qty, 2, ',', '.'),
                        number_format($item->cost_price, 2, ',', '.'),
                        number_format($item->sell_price, 2, ',', '.'),
                        number_format($item->stock_qty * $item->cost_price, 2, ',', '.'),
                        $item->is_active ? 'Ativo' : 'Inativo',
                    ], ';');
                }
            });
    }

    /**
     * @param  resource  $file
     */
    private function exportCustomers(int $tenantId, string $from, string $to, ?int $branchId, $file): void
    {
        fputcsv($file, ['section', 'Clientes'], ';');
        fputcsv($file, ['ID', 'Nome', 'Documento', 'Segmento', 'Telefone', 'Email', 'Status', 'Criado em'], ';');
        $cQuery = Customer::where('tenant_id', $tenantId);
        if ($branchId) {
            $cQuery->whereHas('assignedSeller', fn ($q) => $q->where('branch_id', $branchId));
        }
        $cQuery->chunk(500, function ($items) use ($file) {
            foreach ($items as $item) {
                fputcsv($file, [
                    $item->id,
                    $item->name,
                    $item->document,
                    $item->segment,
                    $item->phone,
                    $item->email,
                    $item->is_active ? 'Ativo' : 'Inativo',
                    $item->created_at ? Carbon::parse($item->created_at)->format('d/m/Y') : '',
                ], ';');
            }
        });
    }
}
