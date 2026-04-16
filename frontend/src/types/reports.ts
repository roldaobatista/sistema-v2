/**
 * Tipos compartilhados para dados de relatórios.
 * Usados pelas tabs de relatório em pages/relatorios/tabs/
 */

/** Dados de relatório de comissões */
export interface CommissionsReportData {
    by_technician?: Array<{ name: string; total: number; Pendente: number; Pago: number }>
    by_status?: Array<{ name: string; value: number }>
    total_commissions?: number
    total_paid?: number
    total_pending?: number
}

/** Dados de relatório de produtividade */
export interface ProductivityReportData {
    technicians?: Array<{ name: string; Trabalho: number; Deslocamento: number; os_count: number; avg_rating: number }>
    efficiency_by_category?: Array<{ name: string; value: number }>
}

/** Dados de relatório de OS */
export interface OsReportData {
    by_status?: Array<{ name: string; value: number; total?: number }>
    by_type?: Array<{ name: string; value: number }>
    by_technician?: Array<{ name: string; completadas: number; abertas: number }>
    avg_completion_hours?: number
    total_os?: number
}

/** Dados de relatório financeiro */
export interface FinancialReportData {
    expenses_by_category?: Array<{ name: string; value: number }>
    monthly_flow?: Array<{ month: string; receita: number; despesa: number }>
    total_revenue?: number
    total_expenses?: number
    net_profit?: number
}

/** Dados de relatório de clientes */
export interface CustomersReportData {
    top_by_revenue?: Array<{ name: string; revenue: number }>
    by_segment?: Array<{ name: string; value: number }>
    total_customers?: number
    new_customers?: number
}

/** Dados de relatório de equipamentos */
export interface EquipmentsReportData {
    by_class?: Array<{ name: string; value: number }>
    top_brands?: Array<{ name: string; value: number }>
    total_equipment?: number
    due_calibrations?: number
}

/** Dados de relatório de chamados */
export interface ServiceCallsReportData {
    by_status?: Array<{ name: string; value: number }>
    by_technician?: Array<{ name: string; total: number; resolved: number }>
    by_priority?: Array<{ name: string; value: number }>
    avg_resolution_hours?: number
}

/** Item com estoque baixo */
export interface LowStockItem {
    id: number
    name: string
    code?: string | null
    stock_qty: number
    stock_min: number
    warehouse?: string | null
}

/** Dados de relatório de estoque */
export interface StockReportData {
    low_stock?: LowStockItem[]
    top_items?: Array<{ name: string; value: number }>
    total_items?: number
    total_value?: number
}

/** Dados de relatório de fornecedores */
export interface SuppliersReportData {
    ranking?: Array<{ name: string; total: number; orders: number }>
    total_suppliers?: number
}

/** Dados de relatório de caixa de técnico */
export interface TechnicianCashReportData {
    funds?: Array<{ name: string; credito: number; debito: number; saldo: number }>
    total_balance?: number
}

/** Dados de relatório de orçamentos */
export interface QuotesReportData {
    by_status?: Array<{ name: string; value: number }>
    conversion_rate?: number
    total_quotes?: number
    total_value?: number
}

/** Dados de relatório CRM */
export interface CrmReportData {
    pipeline?: Array<{ name: string; value: number }>
    health?: Array<{ name: string; value: number }>
    conversion_rate?: number
    total_deals?: number
}

/** Dados de relatório de rentabilidade */
export interface ProfitabilityReportData {
    by_service?: Array<{ name: string; revenue: number; cost: number; margin: number }>
    total_revenue?: number
    total_margin?: number
}

/** Union de todos os report data para ReportsPage */
export type ReportData =
    | CommissionsReportData
    | ProductivityReportData
    | OsReportData
    | FinancialReportData
    | CustomersReportData
    | EquipmentsReportData
    | ServiceCallsReportData
    | StockReportData
    | SuppliersReportData
    | TechnicianCashReportData
    | QuotesReportData
    | CrmReportData
    | ProfitabilityReportData
    | Record<string, unknown>
