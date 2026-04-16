import api, { unwrapData } from './api'
import type { AccountReceivable, AccountPayable, BankAccount, PaymentMethod } from '@/types/financial'

export const financialApi = {
  receivables: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: AccountReceivable[]; current_page?: number; last_page?: number; total?: number }>('/accounts-receivable', { params }),
    detail: (id: number) =>
      api.get<{ data: AccountReceivable }>(`/accounts-receivable/${id}`),
    summary: () =>
      api.get('/accounts-receivable-summary'),
    create: (data: Record<string, unknown>) =>
      api.post('/accounts-receivable', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/accounts-receivable/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/accounts-receivable/${id}`),
    pay: (id: number, data: Record<string, unknown>) =>
      api.post(`/accounts-receivable/${id}/pay`, data),
    cancel: (id: number) =>
      api.put(`/accounts-receivable/${id}`, { status: 'cancelled' }),
    generateFromOs: (data: { work_order_id: string; due_date: string; payment_method: string }) =>
      api.post('/accounts-receivable/generate-from-os', data),
    generateInstallments: (data: Record<string, unknown>) =>
      api.post('/accounts-receivable/installments', data),
  },
  payables: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: AccountPayable[]; current_page?: number; last_page?: number; total?: number }>('/accounts-payable', { params }),
    summary: () =>
      api.get('/accounts-payable-summary'),
    create: (data: Record<string, unknown>) =>
      api.post('/accounts-payable', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/accounts-payable/${id}`, data),
    detail: (id: number) =>
      api.get(`/accounts-payable/${id}`),
    pay: (id: number, data: Record<string, unknown>) =>
      api.post(`/accounts-payable/${id}/pay`, data),
    cancel: (id: number) =>
      api.put(`/accounts-payable/${id}`, { status: 'cancelled' }),
    destroy: (id: number) =>
      api.delete(`/accounts-payable/${id}`),
  },
  payablesCategories: {
    list: () =>
      api.get('/account-payable-categories'),
    create: (data: Record<string, unknown>) =>
      api.post('/account-payable-categories', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/account-payable-categories/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/account-payable-categories/${id}`),
  },
  expenses: {
    list: (params?: Record<string, unknown>) =>
      api.get('/expenses', { params }),
    summary: () =>
      api.get('/expense-summary'),
    categories: () =>
      api.get('/expense-categories'),
    analytics: () =>
      api.get('/expense-analytics'),
    create: (formData: FormData) =>
      api.post('/expenses', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    update: (id: number, formData: FormData) =>
    {
      formData.append('_method', 'PUT');
      return api.post(`/expenses/${id}`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    },
    batchStatus: (data: { expense_ids: number[]; status: string; rejection_reason?: string }) =>
      api.post('/expenses/batch-status', data),
    updateStatus: (id: number, data: { status: string; rejection_reason?: string }) =>
      api.put(`/expenses/${id}/status`, data),
    destroy: (id: number) =>
      api.delete(`/expenses/${id}`),
    duplicate: (id: number) =>
      api.post(`/expenses/${id}/duplicate`),
    review: (id: number) =>
      api.post(`/expenses/${id}/review`),
    history: (id: number) =>
      api.get(`/expenses/${id}/history`),
    detail: (id: number) =>
      api.get(`/expenses/${id}`),
  },
  bankAccounts: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data?: BankAccount[] }>('/bank-accounts', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/bank-accounts', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/bank-accounts/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/bank-accounts/${id}`),
  },
  paymentMethods: {
    list: () =>
      api.get<{ data?: PaymentMethod[] }>('/payment-methods'),
    create: (data: Record<string, unknown>) =>
      api.post('/payment-methods', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/payment-methods/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/payment-methods/${id}`),
  },
  chartOfAccounts: {
    list: (params?: Record<string, unknown>) =>
      api.get('/chart-of-accounts', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/chart-of-accounts', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/chart-of-accounts/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/chart-of-accounts/${id}`),
  },
  fundTransfers: {
    list: (params?: Record<string, unknown>) =>
      api.get('/fund-transfers', { params }),
    summary: () =>
      api.get('/fund-transfers/summary'),
    create: (data: Record<string, unknown>) =>
      api.post('/fund-transfers', data),
    cancel: (id: number) =>
      api.post(`/fund-transfers/${id}/cancel`),
  },
  reconciliationRules: {
    list: (params?: Record<string, unknown>) =>
      api.get('/reconciliation-rules', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/reconciliation-rules', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/reconciliation-rules/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/reconciliation-rules/${id}`),
    toggle: (id: number) =>
      api.post(`/reconciliation-rules/${id}/toggle`),
    test: (data: Record<string, unknown>) =>
      api.post('/reconciliation-rules/test', data),
  },
  invoices: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: unknown[]; current_page?: number; last_page?: number; total?: number }>('/invoices', { params }),
    detail: (id: number) =>
      api.get(`/invoices/${id}`),
    metadata: () =>
      api.get('/invoices/metadata'),
    create: (data: Record<string, unknown>) =>
      api.post('/invoices', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/invoices/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/invoices/${id}`),
  },
  commissions: {
    rules: (params?: Record<string, unknown>) =>
      api.get('/commission-rules', { params }),
    showRule: (id: number) =>
      api.get(`/commission-rules/${id}`),
    storeRule: (data: Record<string, unknown>) =>
      api.post('/commission-rules', data),
    updateRule: (id: number, data: Record<string, unknown>) =>
      api.put(`/commission-rules/${id}`, data),
    destroyRule: (id: number) =>
      api.delete(`/commission-rules/${id}`),
    users: () =>
      api.get('/commission-users'),
    calculationTypes: () =>
      api.get('/commission-calculation-types'),
    events: (params?: Record<string, unknown>) =>
      api.get('/commission-events', { params }),
    settlements: (params?: Record<string, unknown>) =>
      api.get('/commission-settlements', { params }),
    summary: () =>
      api.get('/commission-summary'),
    generateForWorkOrder: (data: Record<string, unknown>) =>
      api.post('/commission-events/generate', data),
    batchGenerateForWorkOrders: (data: Record<string, unknown>) =>
      api.post('/commission-events/batch-generate', data),
    simulate: (data: Record<string, unknown>) =>
      api.post('/commission-simulate', data),
    updateEventStatus: (id: number, data: Record<string, unknown>) =>
      api.put(`/commission-events/${id}/status`, data),
    batchUpdateStatus: (data: Record<string, unknown>) =>
      api.post('/commission-events/batch-status', data),
    splitEvent: (id: number, data: Record<string, unknown>) =>
      api.post(`/commission-events/${id}/splits`, data),
    exportEvents: (params?: Record<string, unknown>) =>
      api.get('/commission-events/export', { params, responseType: 'blob' }),
    eventSplits: (id: number) =>
      api.get(`/commission-events/${id}/splits`),
    closeSettlement: (data: Record<string, unknown>) =>
      api.post('/commission-settlements/close', data),
    paySettlement: (id: number, data?: Record<string, unknown>) =>
      api.post(`/commission-settlements/${id}/pay`, data),
    reopenSettlement: (id: number) =>
      api.post(`/commission-settlements/${id}/reopen`),
    approveSettlement: (id: number) =>
      api.post(`/commission-settlements/${id}/approve`),
    rejectSettlement: (id: number, data?: Record<string, unknown>) =>
      api.post(`/commission-settlements/${id}/reject`, data),
    balanceSummary: () =>
      api.get('/commission-settlements/balance-summary'),
    exportSettlements: (params?: Record<string, unknown>) =>
      api.get('/commission-settlements/export', { params, responseType: 'blob' }),
    downloadStatement: (params?: Record<string, unknown>) =>
      api.get('/commission-statement/pdf', { params, responseType: 'blob' }),
    dashboard: {
      overview: (params?: Record<string, unknown>) => api.get('/commission-dashboard/overview', { params }),
      ranking: (params?: Record<string, unknown>) => api.get('/commission-dashboard/ranking', { params }),
      evolution: (params?: Record<string, unknown>) => api.get('/commission-dashboard/evolution', { params }),
      byRule: (params?: Record<string, unknown>) => api.get('/commission-dashboard/by-rule', { params }),
      byRole: (params?: Record<string, unknown>) => api.get('/commission-dashboard/by-role', { params }),
    },
    disputes: {
      list: (params?: Record<string, unknown>) => api.get('/commission-disputes', { params }),
      show: (id: number) => api.get(`/commission-disputes/${id}`),
      store: (data: Record<string, unknown>) => api.post('/commission-disputes', data),
      resolve: (id: number, data: Record<string, unknown>) => api.post(`/commission-disputes/${id}/resolve`, data),
      destroy: (id: number) => api.delete(`/commission-disputes/${id}`),
    },
    goals: {
      list: (params?: Record<string, unknown>) => api.get('/commission-goals', { params }),
      store: (data: Record<string, unknown>) => api.post('/commission-goals', data),
      update: (id: number, data: Record<string, unknown>) => api.put(`/commission-goals/${id}`, data),
      refresh: (id: number) => api.post(`/commission-goals/${id}/refresh`),
      destroy: (id: number) => api.delete(`/commission-goals/${id}`),
    },
    campaigns: {
      list: (params?: Record<string, unknown>) => api.get('/commission-campaigns', { params }),
      store: (data: Record<string, unknown>) => api.post('/commission-campaigns', data),
      update: (id: number, data: Record<string, unknown>) => api.put(`/commission-campaigns/${id}`, data),
      destroy: (id: number) => api.delete(`/commission-campaigns/${id}`),
    },
    recurring: {
      list: (params?: Record<string, unknown>) => api.get('/recurring-commissions', { params }),
      store: (data: Record<string, unknown>) => api.post('/recurring-commissions', data),
      updateStatus: (id: number, data: Record<string, unknown>) => api.put(`/recurring-commissions/${id}/status`, data),
      processMonthly: () => api.post('/recurring-commissions/process-monthly'),
      destroy: (id: number) => api.delete(`/recurring-commissions/${id}`),
    },
    my: {
      events: (params?: Record<string, unknown>) => api.get('/my/commission-events', { params }),
      settlements: (params?: Record<string, unknown>) => api.get('/my/commission-settlements', { params }),
      statementDownload: (params?: Record<string, unknown>) => api.get('/my/commission-statements/download', { params, responseType: 'blob' }),
      summary: (params?: Record<string, unknown>) => api.get('/my/commission-summary', { params }),
      disputes: (params?: Record<string, unknown>) => api.get('/my/commission-disputes', { params }),
    },
  },
  consolidated: () =>
    api.get('/financial/consolidated').then(r => unwrapData(r)),
  dre: (params: { from: string; to: string }) =>
    api.get('/financial/dre', { params }),
  agingReport: () =>
    api.get('/financial/aging-report'),
  expenseAllocation: (params: { from: string; to: string }) =>
    api.get('/financial/expense-allocation', { params }),
  cashFlowWeekly: (params: Record<string, unknown>) =>
    api.get('/financial/cash-flow-weekly', { params }),
  checks: {
    list: (params?: Record<string, unknown>) =>
      api.get('/financial/checks', { params }),
    store: (data: Record<string, unknown>) =>
      api.post('/financial/checks', data),
    updateStatus: (id: number, data: { status: string }) =>
      api.patch(`/financial/checks/${id}/status`, data),
  },
  supplierContracts: {
    list: (params?: Record<string, unknown>) =>
      api.get('/financial/supplier-contracts', { params }),
    store: (data: Record<string, unknown>) =>
      api.post('/financial/supplier-contracts', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/financial/supplier-contracts/${id}`, data),
    destroy: (id: number) =>
      api.delete(`/financial/supplier-contracts/${id}`),
  },
  supplierAdvances: {
    list: (params?: Record<string, unknown>) =>
      api.get('/financial/supplier-advances', { params }),
    store: (data: Record<string, unknown>) =>
      api.post('/financial/supplier-advances', data),
  },
  reimbursements: {
    list: (params?: Record<string, unknown>) =>
      api.get('/financial/expense-reimbursements', { params }),
    approve: (expenseId: number) =>
      api.post(`/financial/expense-reimbursements/${expenseId}/approve`),
  },
  collectionAutomation: {
    rules: (params?: Record<string, unknown>) =>
      api.get('/financial/collection-rules', { params }),
    summary: () =>
      api.get('/collection/summary'),
    actions: () =>
      api.get('/collection/actions'),
    runEngine: () =>
      api.post('/collection/run-engine'),
  },
  debtRenegotiation: {
    list: (params?: Record<string, unknown>) =>
      api.get('/debt-renegotiations', { params }),
    show: (id: number) =>
      api.get(`/debt-renegotiations/${id}`),
    store: (data: Record<string, unknown>) =>
      api.post('/debt-renegotiations', data),
    approve: (id: number) =>
      api.post(`/debt-renegotiations/${id}/approve`),
    cancel: (id: number) =>
      api.post(`/debt-renegotiations/${id}/cancel`),
  },
  payments: {
    list: (params?: Record<string, unknown>) =>
      api.get('/payments', { params }),
    summary: () =>
      api.get('/payment-summary'),
    destroy: (id: number) =>
      api.delete(`/payments/${id}`),
  },
  paymentReceipts: {
    list: (params?: Record<string, unknown>) =>
      api.get('/payment-receipts', { params }),
    show: (id: number) =>
      api.get(`/payment-receipts/${id}`),
    downloadPdf: (id: number) =>
      api.get(`/payment-receipts/${id}/pdf`, { responseType: 'blob' }),
  },
  receivablesSimulator: {
    simulate: (data: Record<string, unknown>) =>
      api.post('/financial/receivables-simulator', data),
  },
  taxCalculator: {
    calculate: (data: Record<string, unknown>) =>
      api.post('/financial/tax-calculation', data),
  },
  reconciliation: {
    summary: (params?: Record<string, unknown>) =>
      api.get('/bank-reconciliation/summary', { params }),
    statements: (params?: Record<string, unknown>) =>
      api.get('/bank-reconciliation/statements', { params }),
    entries: (statementId: number, params?: Record<string, unknown>) =>
      api.get(`/bank-reconciliation/statements/${statementId}/entries`, { params }),
    suggestions: (entryId: number) =>
      api.get(`/bank-reconciliation/entries/${entryId}/suggestions`),
    importStatement: (formData: FormData) =>
      api.post('/bank-reconciliation/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    match: (entryId: number, data: Record<string, unknown>) =>
      api.post(`/bank-reconciliation/entries/${entryId}/match`, data),
    ignore: (entryId: number) =>
      api.post(`/bank-reconciliation/entries/${entryId}/ignore`),
    unmatch: (entryId: number) =>
      api.post(`/bank-reconciliation/entries/${entryId}/unmatch`),
    bulkAction: (data: Record<string, unknown>) =>
      api.post('/bank-reconciliation/bulk-action', data),
    dashboard: (params?: Record<string, unknown>) =>
      api.get('/bank-reconciliation/dashboard', { params }),
    exportStatement: (statementId: number, format: string) =>
      api.get(`/bank-reconciliation/statements/${statementId}/export`, { params: { format }, responseType: 'blob' }),
    exportPdf: (statementId: number) =>
      api.get(`/bank-reconciliation/statements/${statementId}/export-pdf`, { responseType: 'blob' }),
    searchFinancials: (params?: Record<string, unknown>) =>
      api.get('/bank-reconciliation/search-financials', { params }),
    entryHistory: (entryId: number) =>
      api.get(`/bank-reconciliation/entries/${entryId}/history`),
    destroyStatement: (statementId: number) =>
      api.delete(`/bank-reconciliation/statements/${statementId}`),
  },
  dreComparativo: (params?: Record<string, unknown>) =>
    api.get('/cash-flow/dre-comparativo', { params }),
  financeAdvanced: {
    importCnab: (formData: FormData) =>
      api.post('/finance-advanced/cnab/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    cashFlowProjection: (params?: Record<string, unknown>) =>
      api.get('/finance-advanced/cash-flow/projection', { params }),
    simulateInstallment: (data: Record<string, unknown>) =>
      api.post('/finance-advanced/installment/simulate', data),
    createInstallment: (data: Record<string, unknown>) =>
      api.post('/finance-advanced/installment/create', data),
    delinquencyDashboard: () =>
      api.get('/finance-advanced/delinquency/dashboard'),
    partialPayment: (receivableId: number, data: Record<string, unknown>) =>
      api.post(`/finance-advanced/receivables/${receivableId}/partial-payment`, data),
    dreByCostCenter: (params?: Record<string, unknown>) =>
      api.get('/finance-advanced/dre/cost-center', { params }),
  },
  costCenters: {
    list: (params?: Record<string, unknown>) =>
      api.get('/cost-centers', { params }),
  },
}
