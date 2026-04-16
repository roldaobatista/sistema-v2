import api, { unwrapData } from './api'
import type { Quote, QuoteTimelineEntry, QuoteInstallment, QuoteSummary, QuoteTemplate, AdvancedQuoteSummary } from '@/types/quote'

function buildRejectPayload(reason?: string): Record<string, string> {
  const normalized = reason?.trim()
  return normalized ? { reason: normalized } : {}
}

export const quoteApi = {
  list: (params?: Record<string, string | number | boolean | undefined>) =>
    api.get<{ data: Quote[]; last_page?: number; total?: number }>('/quotes', { params }),

  summary: () =>
    api.get<{ data?: QuoteSummary }>('/quotes-summary').then((response) => unwrapData<QuoteSummary>(response)),

  advancedSummary: () =>
    api.get<{ data?: AdvancedQuoteSummary }>('/quotes-advanced-summary').then((response) => unwrapData<AdvancedQuoteSummary>(response)),

  tags: () =>
    api.get<{ data?: { id: number; name: string; color: string }[] }>('/quote-tags').then((response) => unwrapData<{ id: number; name: string; color: string }[]>(response)),

  templates: () =>
    api.get<{ data?: QuoteTemplate[] }>('/quote-templates').then((response) => unwrapData<QuoteTemplate[]>(response)),

  export: (params?: { status?: string }) =>
    api.get('/quotes-export', { params, responseType: 'blob' }),

  detail: (id: number) =>
    api.get<{ data?: Quote }>(`/quotes/${id}`).then((response) => unwrapData<Quote>(response)),

  timeline: (id: number) =>
    api.get<{ data?: QuoteTimelineEntry[] }>(`/quotes/${id}/timeline`).then((response) => unwrapData<QuoteTimelineEntry[]>(response)),

  installments: (id: number) =>
    api.get<{ data?: QuoteInstallment[] }>(`/quotes/${id}/installments`).then((response) => unwrapData<QuoteInstallment[]>(response)),

  requestInternalApproval: (id: number) =>
    api.post(`/quotes/${id}/request-internal-approval`),

  internalApprove: (id: number) =>
    api.post(`/quotes/${id}/internal-approve`),

  send: (id: number) =>
    api.post(`/quotes/${id}/send`),

  getWhatsAppUrl: (id: number, phone?: string) =>
    api.get<{ data?: { url?: string }; url?: string }>(`/quotes/${id}/whatsapp`, { params: phone ? { phone } : undefined }),

  approve: (id: number, data?: { approval_channel?: string; approval_notes?: string; terms_accepted?: boolean }) =>
    api.post(`/quotes/${id}/approve`, data),

  reject: (id: number, reason?: string) =>
    api.post(`/quotes/${id}/reject`, buildRejectPayload(reason)),

  convertToOs: (id: number, is_installation_testing: boolean) =>
    api.post(`/quotes/${id}/convert-to-os`, { is_installation_testing }),

  convertToChamado: (id: number, is_installation_testing: boolean) =>
    api.post(`/quotes/${id}/convert-to-chamado`, { is_installation_testing }),

  approveAfterTest: (id: number) =>
    api.post(`/quotes/${id}/approve-after-test`),

  renegotiate: (id: number) =>
    api.post(`/quotes/${id}/renegotiate`),

  revertRenegotiation: (id: number, target_status: string) =>
    api.post(`/quotes/${id}/revert-renegotiation`, { target_status }),

  duplicate: (id: number) =>
    api.post<{ data?: { id?: number }; id?: number }>(`/quotes/${id}/duplicate`),

  destroy: (id: number) =>
    api.delete(`/quotes/${id}`),

  reopen: (id: number) =>
    api.post(`/quotes/${id}/reopen`),

  getPdf: (id: number, inline?: boolean) =>
    api.get(`/quotes/${id}/pdf`, { params: inline ? { inline: 1 } : undefined, responseType: 'blob' }),

  sendEmail: (id: number, data: { recipient_email: string; recipient_name?: string; message?: string }) =>
    api.post(`/quotes/${id}/email`, data),

  update: (id: number, data: Record<string, unknown>) =>
    api.put(`/quotes/${id}`, data),

  updateItem: (itemId: number, data: Record<string, unknown>) =>
    api.put(`/quote-items/${itemId}`, data),

  deleteItem: (itemId: number) =>
    api.delete(`/quote-items/${itemId}`),

  addEquipmentItem: (quoteEquipId: number, payload: Record<string, unknown>) =>
    api.post(`/quote-equipments/${quoteEquipId}/items`, payload),

  addEquipment: (quoteId: number, equipmentId: number) =>
    api.post(`/quotes/${quoteId}/equipments`, { equipment_id: equipmentId }),

  removeEquipment: (quoteId: number, quoteEquipId: number) =>
    api.delete(`/quotes/${quoteId}/equipments/${quoteEquipId}`),

  create: (data: Record<string, unknown>) =>
    api.post<{ data?: { id?: number }; id?: number }>('/quotes', data),

  createFromTemplate: (templateId: number, data: { customer_id: number } & Record<string, unknown>) =>
    api.post<{ data: Quote }>(`/quote-templates/${templateId}/create-quote`, data),

  runAction: (id: number, endpoint: string, payload?: Record<string, string | number | boolean>) =>
    api.post(`/quotes/${id}/${endpoint}`, payload),

  bulkAction: (data: { ids: number[]; action: 'delete' | 'approve' | 'send' | 'export' }) =>
    api.post<{ data: { success: number; failed: number; errors: { quote_id?: number; quote_number?: string; error: string }[] } }>('/quotes/bulk-action', data),

  // --- Revisões ---
  revisions: (quoteId: number) =>
    api.get<{ data?: Record<string, unknown>[] }>(`/quotes/${quoteId}/revisions`).then((response) => unwrapData<Record<string, unknown>[]>(response)),

  // --- Tags por quote ---
  quoteTags: (quoteId: number) =>
    api.get<{ data?: { id: number; name: string; color: string }[] }>(`/quotes/${quoteId}/tags`).then((response) => unwrapData<{ id: number; name: string; color: string }[]>(response)),

  // --- Tags ---
  syncTags: (quoteId: number, tagIds: number[]) =>
    api.post(`/quotes/${quoteId}/tags`, { tag_ids: tagIds }),

  storeTag: (data: { name: string; color?: string }) =>
    api.post('/quote-tags', data),

  destroyTag: (tagId: number) =>
    api.delete(`/quote-tags/${tagId}`),

  // --- Templates ---
  storeTemplate: (data: Record<string, unknown>) =>
    api.post('/quote-templates', data),

  updateTemplate: (id: number, data: Record<string, unknown>) =>
    api.put(`/quote-templates/${id}`, data),

  destroyTemplate: (id: number) =>
    api.delete(`/quote-templates/${id}`),

  // --- Equipamentos ---
  updateEquipment: (equipId: number, data: Record<string, unknown>) =>
    api.put(`/quote-equipments/${equipId}`, data),

  // --- Fotos ---
  addPhoto: (quoteId: number, data: FormData) =>
    api.post(`/quotes/${quoteId}/photos`, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  removePhoto: (photoId: number) =>
    api.delete(`/quote-photos/${photoId}`),

  // --- Aprovacao nivel 2 ---
  approveLevel2: (quoteId: number) =>
    api.post(`/quotes/${quoteId}/approve-level2`),

  // --- Comparacao ---
  compareQuotes: (ids: number[]) =>
    api.post('/quotes/compare', { ids }),

  // --- Faturamento ---
  invoice: (quoteId: number) =>
    api.post(`/quotes/${quoteId}/invoice`),

  // --- Fiscal ---
  emitNfe: (quoteId: number) =>
    api.post(`/fiscal/nfe/from-quote/${quoteId}`),
}
