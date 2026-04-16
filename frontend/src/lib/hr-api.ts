import api from './api'
import type {
  TimeClockEntry,
  TimeClockAdjustment,
  GeofenceLocation,
  JourneyRule,
  JourneyEntry,
  Holiday,
  LeaveRequest,
  VacationBalance,
  EmployeeDocument,
  EmployeeBenefit,
  OnboardingTemplate,
  OnboardingChecklist,
  ClockComprovante,
  EspelhoPonto,
  Payroll,
  PayslipRecord,
  Rescission,
  ESocialEvent,
  ESocialCertificate,
  ESocialDashboard,
  HourBankTransaction,
  FiscalIntegrityResponse,
} from '@/types/hr'

export const hrApi = {
  // Clock
  clock: {
    in: (data: FormData) =>
      api.post('/hr/advanced/clock-in', data),
    out: (data: { latitude?: number; longitude?: number; notes?: string }) =>
      api.post('/hr/advanced/clock-out', data),
    breakStart: (data: { latitude?: number; longitude?: number }) =>
      api.post('/hr/advanced/break-start', data),
    breakEnd: (data: { latitude?: number; longitude?: number }) =>
      api.post('/hr/advanced/break-end', data),
    status: () =>
      api.get('/hr/advanced/clock/status'),
    history: (params?: Record<string, unknown>) =>
      api.get<{ data: TimeClockEntry[] }>('/hr/advanced/clock/history', { params }),
    pending: () =>
      api.get<{ data: TimeClockEntry[] }>('/hr/advanced/clock/pending'),
    approve: (id: number) =>
      api.post(`/hr/advanced/clock/${id}/approve`),
    reject: (id: number, data: { rejection_reason: string }) =>
      api.post(`/hr/advanced/clock/${id}/reject`, data),
  },

  // Adjustments
  adjustments: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: TimeClockAdjustment[] }>('/hr/adjustments', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/hr/adjustments', data),
    approve: (id: number) =>
      api.post(`/hr/adjustments/${id}/approve`),
    reject: (id: number, data: { rejection_reason: string }) =>
      api.post(`/hr/adjustments/${id}/reject`, data),
  },

  // Geofences
  geofences: {
    list: () =>
      api.get<{ data: GeofenceLocation[] }>('/hr/geofences'),
    create: (data: Record<string, unknown>) =>
      api.post('/hr/geofences', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/hr/geofences/${id}`, data),
    delete: (id: number) =>
      api.delete(`/hr/geofences/${id}`),
  },

  // Journey
  journey: {
    rules: {
      list: () =>
        api.get<{ data: JourneyRule[] }>('/hr/journey-rules'),
      create: (data: Record<string, unknown>) =>
        api.post('/hr/journey-rules', data),
      update: (id: number, data: Record<string, unknown>) =>
        api.put(`/hr/journey-rules/${id}`, data),
      delete: (id: number) =>
        api.delete(`/hr/journey-rules/${id}`),
    },
    entries: (params: Record<string, unknown>) =>
      api.get<{ data: JourneyEntry[] }>('/hr/journey-entries', { params }),
    calculate: (data: { user_id: number; year_month: string }) =>
      api.post('/hr/journey/calculate', data),
    hourBank: (params: { user_id: number }) =>
      api.get('/hr/hour-bank/balance', { params }),
    hourBankTransactions: (params: { user_id: number; page?: number }) =>
      api.get<{ data: HourBankTransaction[] }>('/hr/hour-bank/transactions', { params }),
  },

  // Fiscal / Compliance (Portaria 671/2021)
  fiscal: {
    exportAfd: (params: { start_date: string; end_date: string }) =>
      api.get('/hr/fiscal/afd', { params, responseType: 'blob' }),
    exportAep: (userId: number, year: number, month: number) =>
      api.get(`/hr/fiscal/aep/${userId}/${year}/${month}`),
    verifyIntegrity: (params?: { user_id?: number; start_date?: string; end_date?: string }) =>
      api.get<{ data: FiscalIntegrityResponse }>('/hr/fiscal/integrity', { params }),
  },

  // Holidays
  holidays: {
    list: () =>
      api.get<{ data: Holiday[] }>('/hr/holidays'),
    create: (data: Record<string, unknown>) =>
      api.post('/hr/holidays', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/hr/holidays/${id}`, data),
    delete: (id: number) =>
      api.delete(`/hr/holidays/${id}`),
    importNational: (data: { year: number }) =>
      api.post('/hr/holidays/import-national', data),
  },

  // Leaves
  leaves: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: LeaveRequest[] }>('/hr/leaves', { params }),
    create: (data: Record<string, unknown> | FormData) =>
      api.post('/hr/leaves', data),
    approve: (id: number) =>
      api.post(`/hr/leaves/${id}/approve`),
    reject: (id: number, data: { rejection_reason: string }) =>
      api.post(`/hr/leaves/${id}/reject`, data),
  },

  // Vacation balances
  vacations: {
    balances: (params?: Record<string, unknown>) =>
      api.get<{ data: VacationBalance[] }>('/hr/vacation-balances', { params }),
  },

  // Documents
  documents: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: EmployeeDocument[] }>('/hr/documents', { params }),
    create: (data: FormData) =>
      api.post('/hr/documents', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/hr/documents/${id}`, data),
    delete: (id: number) =>
      api.delete(`/hr/documents/${id}`),
    expiring: (params?: { days?: number }) =>
      api.get<{ data: EmployeeDocument[] }>('/hr/documents/expiring', { params }),
  },

  // Onboarding
  onboarding: {
    templates: {
      list: () =>
        api.get<{ data: OnboardingTemplate[] }>('/hr/onboarding/templates'),
      create: (data: Record<string, unknown>) =>
        api.post('/hr/onboarding/templates', data),
      update: (id: number, data: Record<string, unknown>) =>
        api.put(`/hr/onboarding/templates/${id}`, data),
      delete: (id: number) =>
        api.delete(`/hr/onboarding/templates/${id}`),
    },
    checklists: {
      list: (params?: Record<string, unknown>) =>
        api.get<{ data: OnboardingChecklist[] }>('/hr/onboarding/checklists', { params }),
    },
    start: (data: { user_id: number; template_id: number }) =>
      api.post('/hr/onboarding/start', data),
    completeItem: (_checklistId: number, itemId: number) =>
      api.post(`/hr/onboarding/items/${itemId}/complete`),
  },

  // Benefits
  benefits: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: EmployeeBenefit[] }>('/hr/benefits', { params }),
    create: (data: Record<string, unknown>) =>
      api.post('/hr/benefits', data),
    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/hr/benefits/${id}`, data),
    delete: (id: number) =>
      api.delete(`/hr/benefits/${id}`),
  },

  // Performance
  performance: {
    reviews: (params?: Record<string, unknown>) =>
      api.get('/hr/performance-reviews', { params }),
    createReview: (data: Record<string, unknown>) =>
      api.post('/hr/performance-reviews', data),
    feedback: (params?: Record<string, unknown>) =>
      api.get('/hr/continuous-feedback', { params }),
    createFeedback: (data: Record<string, unknown>) =>
      api.post('/hr/continuous-feedback', data),
  },

  // Recruitment
  recruitment: {
    postings: (params?: Record<string, unknown>) =>
      api.get('/hr/job-postings', { params }),
    createPosting: (data: Record<string, unknown>) =>
      api.post('/hr/job-postings', data),
    candidates: (jobPostingId: number, params?: Record<string, unknown>) =>
      api.get(`/hr/job-postings/${jobPostingId}/candidates`, { params }),
    createCandidate: (jobPostingId: number, data: Record<string, unknown>) =>
      api.post(`/hr/job-postings/${jobPostingId}/candidates`, data),
  },

  // Payroll
  payroll: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: Payroll[] }>('/hr/payroll', { params }),
    create: (data: { reference_month: string; type: string; notes?: string }) =>
      api.post<{ data: Payroll }>('/hr/payroll', data),
    show: (id: number) =>
      api.get<{ data: Payroll }>(`/hr/payroll/${id}`),
    calculate: (id: number) =>
      api.post<{ data: Payroll }>(`/hr/payroll/${id}/calculate`),
    approve: (id: number) =>
      api.post<{ data: Payroll }>(`/hr/payroll/${id}/approve`),
    markPaid: (id: number) =>
      api.post<{ data: Payroll }>(`/hr/payroll/${id}/mark-paid`),
    generatePayslips: (id: number) =>
      api.post(`/hr/payroll/${id}/generate-payslips`),
  },

  // Payslips (employee self-service)
  payslips: {
    my: (params?: Record<string, unknown>) =>
      api.get<{ data: PayslipRecord[] }>('/hr/my-payslips', { params }),
    show: (id: number) =>
      api.get<{ data: PayslipRecord }>(`/hr/payslips/${id}`),
  },

  // Rescissions (Rescisão)
  rescissions: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data: Rescission[] }>('/hr/rescissions', { params }),
    show: (id: number) =>
      api.get<{ data: Rescission }>(`/hr/rescissions/${id}`),
    create: (data: { user_id: number; type: string; termination_date: string; notice_type?: string; notes?: string }) =>
      api.post<{ data: Rescission }>('/hr/rescissions', data),
    approve: (id: number) =>
      api.post<{ data: Rescission }>(`/hr/rescissions/${id}/approve`),
    markPaid: (id: number) =>
      api.post<{ data: Rescission }>(`/hr/rescissions/${id}/mark-paid`),
    trct: (id: number) =>
      api.get(`/hr/rescissions/${id}/trct`, { responseType: 'blob' }),
  },

  // Compliance (Portaria 671)
  compliance: {
    verifyIntegrity: (params?: { user_id?: number }) =>
      api.get('/hr/ponto/verify-integrity', { params }),
    comprovante: (id: number) =>
      api.get<{ data: ClockComprovante }>(`/hr/ponto/comprovante/${id}`),
    espelhoPonto: (userId: number, year: number, month: number) =>
      api.get<{ data: EspelhoPonto }>(`/hr/ponto/espelho/${userId}/${year}/${month}`),
    exportAFD: (params: { start_date: string; end_date: string }) =>
      api.get('/hr/ponto/afd/export', { params, responseType: 'blob' }),
    confirmEntry: (entryId: number, data: { method: string }) =>
      api.post(`/hr/compliance/confirm-entry/${entryId}`, data),
    verifyHashIntegrity: (data: { start_date: string; end_date: string }) =>
      api.post('/hr/compliance/verify-integrity', data),
  },

  // Reports
  reports: {
    payrollCost: (params?: { months?: number }) =>
      api.get('/hr/reports/payroll-cost', { params }),
    overtimeTrend: (params?: { months?: number }) =>
      api.get('/hr/reports/overtime-trend', { params }),
    hourBankForecast: () =>
      api.get('/hr/reports/hour-bank-forecast'),
    taxObligations: (params?: { reference_month?: string }) =>
      api.get('/hr/reports/tax-obligations', { params }),
    incomeStatement: (userId: number, year: number) =>
      api.get(`/hr/reports/income-statement/${userId}/${year}`),
    laborCostByProject: (params?: { start_date?: string; end_date?: string }) =>
      api.get('/hr/reports/labor-cost-by-project', { params }),
  },

  // Dashboard
  dashboard: {
    summary: () =>
      api.get('/hr/advanced/dashboard'),
    analytics: (params?: Record<string, unknown>) =>
      api.get('/hr/analytics', { params }),
    widgets: () =>
      api.get('/hr/dashboard/widgets'),
    team: () =>
      api.get('/hr/dashboard/team'),
  },

  // eSocial
  esocial: {
    events: (params?: Record<string, unknown>) =>
      api.get<{ data: ESocialEvent[]; meta: Record<string, unknown> }>('/hr/esocial/events', { params }),
    show: (id: number) =>
      api.get<{ data: ESocialEvent }>(`/hr/esocial/events/${id}`),
    generate: (data: { event_type: string; related_type: string; related_id: number }) =>
      api.post<{ data: ESocialEvent }>('/hr/esocial/events/generate', data),
    sendBatch: (data: { event_ids: number[] }) =>
      api.post<{ data: { batch_id: string; events_sent: number } }>('/hr/esocial/events/send-batch', data),
    checkBatch: (batchId: string) =>
      api.get<{ data: Record<string, unknown> }>(`/hr/esocial/batches/${batchId}`),
    certificates: () =>
      api.get<{ data: ESocialCertificate[] }>('/hr/esocial/certificates'),
    uploadCertificate: (data: FormData) =>
      api.post<{ data: ESocialCertificate }>('/hr/esocial/certificates', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      }),
    dashboard: () =>
      api.get<{ data: ESocialDashboard }>('/hr/esocial/dashboard'),
    excludeEvent: (eventId: number, reason: string) =>
      api.post(`/hr/esocial/events/${eventId}/exclude`, { reason }),
    generateRubricTable: () =>
      api.post('/hr/esocial/rubric-table'),
  },

  // Audit Trail
  auditTrail: {
    byEntry: (entryId: number) => api.get(`/hr/audit-trail/${entryId}`),
    report: (params: { start_date?: string; end_date?: string; action?: string; user_id?: number }) =>
      api.get('/hr/audit-trail/report', { params }),
  },

  // Security
  security: {
    tamperingAttempts: (params?: { start_date?: string; end_date?: string }) =>
      api.get('/hr/security/tampering-attempts', { params }),
  },

  // Tax Tables
  taxTables: {
    list: (year?: number) => api.get('/hr/tax-tables', { params: { year } }),
    store: (data: { type: string; year: number; data: unknown[] }) => api.post('/hr/tax-tables', data),
  },
}
