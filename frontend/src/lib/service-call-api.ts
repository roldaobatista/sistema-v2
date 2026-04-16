import api, { unwrapData } from './api'
import type {
  ServiceCall,
  ServiceCallSummary,
  ServiceCallKpi,
  ServiceCallAssignee,
  ServiceCallComment,
  ServiceCallMapItem,
  ServiceCallAgendaItem,
  ServiceCallAuditEntry,
  ServiceCallTemplate,
  DuplicateCheckResult,
} from '@/types/service-call'

export const serviceCallApi = {
  list: (params?: Record<string, unknown>) =>
    api.get<{ data: ServiceCall[]; meta?: { current_page?: number; last_page?: number; total?: number } }>('/service-calls', { params }),

  detail: (id: number) =>
    api.get<{ data: ServiceCall }>(`/service-calls/${id}`).then((response) => unwrapData<ServiceCall>(response)),

  summary: () =>
    api.get<{ data?: ServiceCallSummary }>('/service-calls-summary').then((response) => unwrapData<ServiceCallSummary>(response)),

  kpi: (params?: { days?: number }) =>
    api.get<{ data?: ServiceCallKpi }>('/service-calls-kpi', { params }).then((response) => unwrapData<ServiceCallKpi>(response)),

  map: (params?: Record<string, unknown>) =>
    api.get<{ data?: ServiceCallMapItem[] }>('/service-calls-map', { params }).then((response) => unwrapData<ServiceCallMapItem[]>(response)),

  assignees: () =>
    api
      .get<{ data?: { technicians: ServiceCallAssignee[]; drivers: ServiceCallAssignee[] } }>('/service-calls-assignees')
      .then((response) => unwrapData<{ technicians: ServiceCallAssignee[]; drivers: ServiceCallAssignee[] }>(response)),

  create: (data: Record<string, unknown>) =>
    api.post<{ data: ServiceCall }>('/service-calls', data),

  update: (id: number, data: Record<string, unknown>) =>
    api.put<{ data: ServiceCall }>(`/service-calls/${id}`, data),

  updateStatus: (id: number, data: { status: string }) =>
    api.put(`/service-calls/${id}/status`, data),

  destroy: (id: number) =>
    api.delete(`/service-calls/${id}`),

  comments: {
    list: (id: number) =>
      api.get<{ data?: ServiceCallComment[] }>(`/service-calls/${id}/comments`).then((response) => unwrapData<ServiceCallComment[]>(response)),

    create: (id: number, data: { content: string }) =>
      api.post(`/service-calls/${id}/comments`, data),
  },

  assign: (id: number, data: Record<string, unknown>) =>
    api.put(`/service-calls/${id}/assign`, data),

  convertToOs: (id: number) =>
    api.post(`/service-calls/${id}/convert-to-os`),

  reschedule: (id: number, data: { scheduled_date: string; reason: string }) =>
    api.post(`/service-calls/${id}/reschedule`, data),

  bulkAction: (data: { ids: number[]; action: string; technician_id?: number; priority?: string }) =>
    api.post('/service-calls/bulk-action', data),

  checkDuplicate: (params: { customer_id: number }) =>
    api
      .get<{ data?: DuplicateCheckResult }>('/service-calls/check-duplicate', { params })
      .then((response) => unwrapData<DuplicateCheckResult>(response)),

  agenda: (params?: Record<string, unknown>) =>
    api.get<{ data?: ServiceCallAgendaItem[] }>('/service-calls-agenda', { params }).then((response) => unwrapData<ServiceCallAgendaItem[]>(response)),

  auditTrail: (id: number) =>
    api.get<{ data?: ServiceCallAuditEntry[] }>(`/service-calls/${id}/audit-trail`).then((response) => unwrapData<ServiceCallAuditEntry[]>(response)),

  export: (params?: Record<string, unknown>) =>
    api.get('/service-calls-export', { params }),

  templates: {
    list: (params?: Record<string, unknown>) =>
      api.get<{ data?: ServiceCallTemplate[] }>('/service-call-templates', { params }).then((response) => unwrapData<ServiceCallTemplate[]>(response)),

    active: () =>
      api.get<{ data?: ServiceCallTemplate[] }>('/service-call-templates/active').then((response) => unwrapData<ServiceCallTemplate[]>(response)),

    create: (data: Record<string, unknown>) =>
      api.post('/service-call-templates', data),

    update: (id: number, data: Record<string, unknown>) =>
      api.put(`/service-call-templates/${id}`, data),

    destroy: (id: number) =>
      api.delete(`/service-call-templates/${id}`),
  },
}
