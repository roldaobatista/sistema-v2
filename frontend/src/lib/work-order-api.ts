import api from './api'
import type { WorkOrder } from '@/types'
import type {
  ChecklistResponsePayload,
  EditFormPayload,
  ItemFormPayload,
  WorkOrderCreatePayload,
  RequestApprovalPayload,
  RespondApprovalPayload,
  CheckinPayload,
  CheckoutPayload,
  SubmitRatingPayload,
  WorkOrderTemplatePayload,
  FiscalNote,
  CostEstimateBreakdown,
  ApprovalEntry,
  ChatMessage,
  TimeLogEntry,
  SatisfactionRating,
} from '@/types/work-order'

type WorkOrderListParams = Record<string, string | number | boolean | null | undefined>
type WorkOrderImportResponse = { data?: { created: number; errors?: string[] } }
type WorkOrderDuplicateResponse = { data?: { id: number }; id?: number }
export type WorkOrderListResponse = {
  data: WorkOrder[]
  last_page?: number
  total?: number
  status_counts?: Record<string, number>
  meta?: {
    total?: number
    last_page?: number
    status_counts?: Record<string, number>
  }
}
type WorkOrderStatusPayload = {
  status: string
  notes?: string
  agreed_payment_method?: string
  agreed_payment_notes?: string
}

export function getWorkOrderListStatusCounts(payload?: WorkOrderListResponse | null): Record<string, number> {
  return payload?.status_counts ?? payload?.meta?.status_counts ?? {}
}

export const workOrderApi = {
  // ─── CRUD ──────────────────────────────────────────────────────────────────
  list: (params?: WorkOrderListParams) =>
    api.get<WorkOrderListResponse>('/work-orders', { params }),

  detail: (id: number) =>
    api.get<{ data: WorkOrder }>(`/work-orders/${id}`),

  create: (data: WorkOrderCreatePayload) =>
    api.post<{ data: WorkOrder }>('/work-orders', data),

  update: (id: number, data: EditFormPayload) =>
    api.put(`/work-orders/${id}`, data),

  destroy: (id: number) =>
    api.delete(`/work-orders/${id}`),

  // ─── Status & Lifecycle ────────────────────────────────────────────────────
  updateStatus: (id: number, data: WorkOrderStatusPayload) =>
    api.post(`/work-orders/${id}/status`, data),

  updateAssignee: (id: number, assignee_id: number) =>
    api.put(`/work-orders/${id}`, { assigned_to: assignee_id }),

  duplicate: (id: number) =>
    api.post<WorkOrderDuplicateResponse>(`/work-orders/${id}/duplicate`),

  reopen: (id: number) =>
    api.post(`/work-orders/${id}/reopen`),

  uninvoice: (id: number) =>
    api.post(`/work-orders/${id}/uninvoice`),

  authorizeDispatch: (id: number) =>
    api.post(`/work-orders/${id}/authorize-dispatch`),

  // ─── Checklist Responses ───────────────────────────────────────────────────
  checklistResponses: (id: number) =>
    api.get(`/work-orders/${id}/checklist-responses`),

  saveChecklistResponses: (id: number, responses: ChecklistResponsePayload[]) =>
    api.post(`/work-orders/${id}/checklist-responses`, { responses }),

  // ─── Items ─────────────────────────────────────────────────────────────────
  addItem: (id: number, data: ItemFormPayload) =>
    api.post(`/work-orders/${id}/items`, data),

  updateItem: (id: number, itemId: number, data: ItemFormPayload) =>
    api.put(`/work-orders/${id}/items/${itemId}`, data),

  deleteItem: (id: number, itemId: number) =>
    api.delete(`/work-orders/${id}/items/${itemId}`),

  // ─── Attachments ───────────────────────────────────────────────────────────
  attachments: (id: number) =>
    api.get(`/work-orders/${id}/attachments`),

  addAttachment: (id: number, formData: FormData) =>
    api.post(`/work-orders/${id}/attachments`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }),

  uploadAttachment: (id: number, data: FormData) =>
    api.post(`/work-orders/${id}/attachments`, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  deleteAttachment: (id: number, attachmentId: number) =>
    api.delete(`/work-orders/${id}/attachments/${attachmentId}`),

  // ─── Signature ─────────────────────────────────────────────────────────────
  signature: (id: number, data: { signature: string; signer_name: string }) =>
    api.post(`/work-orders/${id}/signature`, data),

  // ─── Equipment ─────────────────────────────────────────────────────────────
  attachEquipment: (id: number, equipmentId: number) =>
    api.post(`/work-orders/${id}/equipments`, { equipment_id: equipmentId }),

  detachEquipment: (id: number, equipmentId: number) =>
    api.delete(`/work-orders/${id}/equipments/${equipmentId}`),

  // ─── Cost Estimate ─────────────────────────────────────────────────────────
  costEstimate: (id: number) =>
    api.get<{ data: CostEstimateBreakdown }>(`/work-orders/${id}/cost-estimate`),

  // ─── Parts Kit ─────────────────────────────────────────────────────────────
  applyKit: (id: number, kitId: number) =>
    api.post(`/work-orders/${id}/apply-kit/${kitId}`),

  // ─── PDF ───────────────────────────────────────────────────────────────────
  pdf: (id: number) =>
    api.get(`/work-orders/${id}/pdf`, { responseType: 'blob' }),

  // ─── CSV Import/Export ─────────────────────────────────────────────────────
  importCsv: (formData: FormData) =>
    api.post<WorkOrderImportResponse>('/work-orders-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  exportCsv: (params?: WorkOrderListParams) =>
    api.get('/work-orders-export', { params, responseType: 'blob' }),

  // ─── Templates ─────────────────────────────────────────────────────────────
  listTemplates: () =>
    api.get('/work-order-templates', { params: { per_page: 100 } }),

  detailTemplate: (id: number) =>
    api.get(`/work-order-templates/${id}`),

  createTemplate: (data: WorkOrderTemplatePayload) =>
    api.post('/work-order-templates', data),

  updateTemplate: (id: number, data: WorkOrderTemplatePayload) =>
    api.put(`/work-order-templates/${id}`, data),

  destroyTemplate: (id: number) =>
    api.delete(`/work-order-templates/${id}`),

  // ─── Fiscal — NFs vinculadas à OS ──────────────────────────────────────────
  fiscalNotes: (id: number) =>
    api.get<{ data: FiscalNote[] }>(`/work-orders/${id}/fiscal-notes`),

  emitNfse: (id: number) =>
    api.post(`/fiscal/nfse/from-work-order/${id}`),

  emitNfe: (id: number) =>
    api.post(`/fiscal/nfe/from-work-order/${id}`),

  // ─── Financial — Contas a Receber ──────────────────────────────────────────
  generateReceivable: (id: number, data?: { amount_paid?: number; notes?: string }) =>
    api.post('/accounts-receivable/generate-from-os', { work_order_id: id, ...data }),

  // ─── Stock — Dedução de Estoque ────────────────────────────────────────────
  autoDeductStock: (id: number) =>
    api.post(`/work-orders/${id}/auto-deduct`),

  // ─── Dashboard ─────────────────────────────────────────────────────────────
  dashboardStats: (params?: WorkOrderListParams) =>
    api.get('/work-orders-dashboard-stats', { params }),

  // ─── Audit Trail ───────────────────────────────────────────────────────────
  auditTrail: (id: number) =>
    api.get(`/work-orders/${id}/audit-trail`),

  // ─── Execution ─────────────────────────────────────────────────────────────
  executionTimeline: (id: number) =>
    api.get(`/work-orders/${id}/execution/timeline`),

  executionAction: (id: number, action: string, data?: { latitude?: number; longitude?: number; notes?: string }) =>
    api.post(`/work-orders/${id}/execution/${action}`, data),

  // ─── Chats ─────────────────────────────────────────────────────────────────
  chats: (id: number) =>
    api.get<{ data: ChatMessage[] }>(`/work-orders/${id}/chats`),

  markChatsRead: (id: number) =>
    api.post(`/work-orders/${id}/chats/read`),

  sendChatMessage: (id: number, data: FormData | { message: string }, config?: { headers?: Record<string, string> }) =>
    api.post(`/work-orders/${id}/chats`, data, config),

  // ─── Approvals ─────────────────────────────────────────────────────────────
  approvals: (id: number) =>
    api.get<{ data: ApprovalEntry[] }>(`/work-orders/${id}/approvals`),

  requestApproval: (id: number, data: RequestApprovalPayload) =>
    api.post(`/work-orders/${id}/approvals/request`, data),

  respondApproval: (id: number, approverId: number, action: string, data: RespondApprovalPayload) =>
    api.post(`/work-orders/${id}/approvals/${approverId}/${action}`, data),

  // ─── Displacement / Checkin ────────────────────────────────────────────────
  checkin: (id: number, data: CheckinPayload) =>
    api.post(`/work-orders/${id}/checkin`, data),

  checkout: (id: number, data: CheckoutPayload) =>
    api.post(`/work-orders/${id}/checkout`, data),

  // ─── Time Logs ─────────────────────────────────────────────────────────────
  timeLogs: (id: number) =>
    api.get<{ data: TimeLogEntry[] }>('/work-order-time-logs', { params: { work_order_id: id } }),

  startTimeLog: (data: { work_order_id: number; description?: string }) =>
    api.post('/work-order-time-logs/start', data),

  stopTimeLog: (logId: number) =>
    api.post(`/work-order-time-logs/${logId}/stop`),

  // ─── Satisfaction ──────────────────────────────────────────────────────────
  satisfaction: (id: number) =>
    api.get<{ data: SatisfactionRating | null }>(`/work-orders/${id}/satisfaction`),

  submitRating: (data: SubmitRatingPayload & { work_order_id: number }) =>
    api.post('/operational/nps', data),

  // ─── Photo Checklist ───────────────────────────────────────────────────────
  uploadChecklistPhoto: (id: number, data: FormData) =>
    api.post(`/work-orders/${id}/photo-checklist/upload`, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
}
