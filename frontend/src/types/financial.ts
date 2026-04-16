export type FinancialStatus = 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled' | 'renegotiated' | 'received'

export interface AccountReceivable {
  id: number
  tenant_id?: number
  customer_id?: number
  work_order_id?: number | null
  invoice_id?: number | null
  quote_id?: number | null
  cost_center_id?: number | null
  created_by?: number | null
  chart_of_account_id?: number | null
  description: string
  amount: string
  amount_paid: string
  due_date: string
  paid_at?: string | null
  status: FinancialStatus | string
  payment_method?: string | null
  penalty_amount?: string | null
  interest_amount?: string | null
  discount_amount?: string | null
  notes?: string | null
  nosso_numero?: string | null
  numero_documento?: string | null
  chart_of_account?: { id: number; code: string; name: string; type: string } | null
  cost_center?: { id: number; name: string } | null
  customer: { id: number; name: string }
  work_order?: { id: number; number: string; os_number?: string | null; business_number?: string | null } | null
  quote?: { id: number; quote_number?: string } | null
  invoice?: { id: number; invoice_number: string } | null
  creator?: { id: number; name: string } | null
  payments?: { id: number; amount: string; payment_method: string; payment_date: string; receiver?: { name: string } }[]
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export interface AccountPayable {
  id: number
  tenant_id?: number
  created_by?: number | null
  supplier_id?: number | null
  category_id?: number | null
  chart_of_account_id?: number | null
  work_order_id?: number | null
  cost_center_id?: number | null
  description: string
  amount: string
  amount_paid?: string
  due_date: string
  paid_at?: string | null
  status: FinancialStatus | string
  payment_method?: string | null
  penalty_amount?: string | null
  interest_amount?: string | null
  discount_amount?: string | null
  notes?: string | null
  supplier?: { id: number; name: string } | null
  category?: { id: number; name: string; color?: string | null } | null
  chart_of_account?: { id: number; code: string; name: string; type: string } | null
  cost_center?: { id: number; name: string } | null
  work_order?: { id: number; number: string } | null
  creator?: { id: number; name: string } | null
  payments?: { id: number; amount: string; payment_method: string; payment_date: string; receiver?: { name: string } }[]
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export type InvoiceStatus = 'draft' | 'issued' | 'sent' | 'cancelled'

export type InvoiceFiscalStatus = 'emitting' | 'emitted' | 'failed'

export interface Invoice {
  id: number
  tenant_id?: number
  work_order_id?: number | null
  customer_id?: number | null
  created_by?: number | null
  invoice_number: string
  nf_number?: string | null
  status: InvoiceStatus
  total: number | string
  discount?: number | string | null
  issued_at?: string | null
  due_date?: string | null
  observations?: string | null
  items?: { id: number; description: string; quantity: number; unit_price: string | number; total: string | number }[] | null
  // Fiscal integration
  fiscal_status?: InvoiceFiscalStatus | null
  fiscal_note_key?: string | null
  fiscal_emitted_at?: string | null
  fiscal_error?: string | null
  // Relations
  work_order?: { id: number; number?: string; os_number?: string | null; business_number?: string | null } | null
  customer?: { id: number; name: string } | null
  creator?: { id: number; name: string } | null
  fiscal_note?: { id: number; key?: string; number?: string; status?: string; emitted_at?: string } | null
  accounts_receivable?: AccountReceivable[]
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export interface Payment {
  id: number
  tenant_id?: number
  payable_type?: string
  payable_id?: number
  received_by?: number | null
  amount: string
  payment_method?: string
  payment_date?: string
  notes?: string | null
  receiver?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export interface PaymentSummary {
  total_paid?: string
  total_pending?: string
  count?: number
}

export interface BankAccount {
  id: number
  name: string
  code?: string | null
  bank_name?: string
  agency?: string | null
  account_number?: string | null
  account_type?: string
  pix_key?: string | null
  type?: string | null
  balance?: string | number | null
  is_active?: boolean
  creator?: { id: number; name: string }
  created_at?: string
}

export interface PaymentMethod {
  id: number
  name: string
  code?: string | null
  is_active?: boolean
}

/** Formulário de criação/edição de conta a receber */
export interface AccountReceivableForm {
  customer_id: string
  description: string
  amount: string
  due_date: string
  payment_method: string
  notes: string
  work_order_id: string
  chart_of_account_id: string
}

/** Opção de cliente em combos financeiros */
export interface ReceivableCustomerOption {
  id: number
  name: string
}

/** Opção de OS em combos de conta a receber */
export interface ReceivableWorkOrderOption {
  id: number
  number: string
  os_number?: string | null
  business_number?: string | null
  total?: string | number | null
  customer?: { id: number; name: string } | null
}

/** Categoria de conta a pagar (classificação) */
export interface AccountPayableCategory {
  id: number
  name: string
  color: string | null
  description: string | null
  is_active: boolean
}

/** Conta a pagar (linha listagem com relações) */
export interface AccountPayableRow {
  id: number
  supplier_id: number | null
  category_id: number | null
  chart_of_account_id?: number | null
  chart_of_account?: { id: number; code: string; name: string; type: string } | null
  supplier_relation?: { id: number; name: string } | null
  category_relation?: { id: number; name: string; color?: string } | null
  description: string
  amount: string
  amount_paid: string
  due_date: string
  paid_at: string | null
  status: string
  payment_method: string | null
  notes: string | null
  payments?: { id: number; amount: string; payment_method: string; payment_date: string; receiver: { name: string } }[]
}

/** Formulário de conta a pagar */
export interface AccountPayableForm {
  supplier_id: string
  category_id: string
  chart_of_account_id: string
  description: string
  amount: string
  due_date: string
  payment_method: string
  notes: string
}

/** Resumo do dashboard de contas a pagar */
export interface PayableDashboardSummary {
  pending?: number
  overdue?: number
  recorded_this_month?: number
  paid_this_month?: number
  total_open?: number
}

/** Opção de plano de contas em combos */
export interface ChartOfAccountOption {
  id: number
  code: string
  name: string
}

/** Opções para formulários de renegociação de dívida */
export interface DebtRenegotiationCustomerOption {
  id: number
  name: string
  document?: string
}

export interface DebtRenegotiationReceivableOption {
  id: number
  description: string
  amount: string
  amount_paid: string
  due_date: string
  status: string
  customer?: { id: number; name: string }
}

export interface DebtRenegotiationRecord {
  id: number
  customer_id: number
  description?: string | null
  original_total: string
  negotiated_total: string
  discount_amount: string
  interest_amount: string
  fine_amount: string
  new_installments: number
  first_due_date: string
  notes?: string | null
  status: string
  created_at: string
  approved_at?: string | null
  customer?: { id: number; name: string } | null
  creator?: { id: number; name: string } | null
}

export interface DebtRenegotiationFormValues {
  description: string
  installments: string
  discount_percentage: string
  interest_rate: string
  new_due_date: string
  notes: string
}

export interface DebtRenegotiationPayload {
  customer_id: number
  receivable_ids: number[]
  description?: string
  new_due_date: string
  installments: number
  discount_percentage?: number
  interest_rate?: number
  notes?: string
}

export interface PaymentReceiptRecord {
  id: number
  amount: string
  payment_method: string | null
  payment_date: string | null
  notes?: string | null
  receiver?: { id: number; name: string } | null
  payable?: {
    id: number
    description?: string | null
    customer?: { id: number; name: string } | null
  } | null
}

/** Linha do relatório de alocação de despesas por OS */
export interface ExpenseAllocationRow {
  work_order_id: number
  os_number: string
  customer_name: string
  expense_count: number
  total_expenses: number
  work_order_total: number
  net_margin: number | null
}

/** Resumo de alocação de despesas */
export interface ExpenseAllocationSummary {
  total_expenses_allocated: number
  total_os_count: number
  average_margin: number | null
}

/** Conta bancária (opção em formulários de transferência) */
export interface FundTransferBankAccountOption {
  id: number
  name: string
  bank_name: string
}

/** Técnico (opção em formulários financeiros) */
export interface TechnicianOption {
  id: number
  name: string
}

/** Registro de transferência de fundos */
export interface FundTransferRecord {
  id: number
  amount: string
  transfer_date: string
  payment_method: string
  description: string
  status: 'completed' | 'cancelled'
  bank_account?: FundTransferBankAccountOption
  technician?: TechnicianOption
  creator?: { id: number; name: string }
  created_at: string
}

/** Resumo de transferências por período/técnico */
export interface FundTransferSummary {
  month_total: number
  total_all: number
  by_technician: Array<{
    to_user_id: number
    total: string
    technician?: TechnicianOption
  }>
}

/** Item de conta a pagar na tela de aprovação em lote */
export interface BatchPayableItem {
  id: number
  description: string
  amount: string
  amount_paid: string
  due_date: string
  status: string
  supplier?: { id: number; name: string }
}

/** Regra de conciliação bancária */
export interface ReconciliationRule {
  id: number
  name: string
  match_field: string
  match_operator: string
  match_value: string | null
  match_amount_min: number | null
  match_amount_max: number | null
  action: string
  category: string | null
  priority: number
  is_active: boolean
  times_applied: number
  customer?: { id: number; name: string } | null
  supplier?: { id: number; name: string } | null
  created_at: string
}

/** Resultado do teste de regra de conciliação */
export interface ReconciliationTestResult {
  total_tested: number
  total_matched: number
  sample: Array<{
    id: number
    date: string
    description: string
    amount: number
    type: string
  }>
}
