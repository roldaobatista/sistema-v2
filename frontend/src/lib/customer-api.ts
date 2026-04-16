import api, { unwrapData } from './api'
import type {
  Customer,
  CustomerDocument,
  CustomerDuplicateGroup,
  CustomerListParams,
  CustomerListResponse,
  CustomerOptions,
  CustomerWithContacts,
} from '@/types/customer'

export const customerApi = {
  list: (params?: CustomerListParams) =>
    api.get<CustomerListResponse>('/customers', { params }),

  options: () =>
    api.get<{ data?: CustomerOptions } | CustomerOptions>('/customers/options').then((response) => unwrapData<CustomerOptions>(response) ?? {}),

  detail: (id: number) =>
    api.get<{ data: CustomerWithContacts }>(`/customers/${id}`).then((response) => unwrapData<CustomerWithContacts>(response)),

  create: (data: Record<string, unknown>) =>
    api.post<{ data: Customer }>('/customers', data),

  update: (id: number, data: Record<string, unknown>) =>
    api.put<{ data: Customer }>(`/customers/${id}`, data),

  destroy: (id: number) =>
    api.delete(`/customers/${id}`),

  searchDuplicates: (type: 'name' | 'document' | 'email') =>
    api
      .get<{ data?: CustomerDuplicateGroup[] }>('/customers/search-duplicates', { params: { type } })
      .then((response) => unwrapData<CustomerDuplicateGroup[]>(response) ?? []),

  merge: (data: { primary_id: number; duplicate_ids: number[] }) =>
    api.post<{ message?: string }>('/customers/merge', data),

  documents: (customerId: number) =>
    api.get<{ data?: CustomerDocument[] }>(`/customers/${customerId}/documents`).then((response) => unwrapData<CustomerDocument[]>(response) ?? []),

  createDocument: (customerId: number, formData: FormData) =>
    api.post(`/customers/${customerId}/documents`, formData, { headers: { 'Content-Type': 'multipart/form-data' } }),

  deleteDocument: (docId: number) =>
    api.delete(`/customer-documents/${docId}`),
}
