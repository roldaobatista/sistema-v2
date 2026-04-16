import api, { unwrapData } from './api'
import type { PaginatedResponse } from '@/types/api'
import type { TravelRequest, TravelExpenseReport } from '@/types/travel'

export const travelApi = {
  list: (params?: { per_page?: number }) =>
    api
      .get<PaginatedResponse<TravelRequest>>('/journey/travel-requests', { params })
      .then((r) => r.data),

  show: (id: number) =>
    api
      .get<{ data: TravelRequest }>(`/journey/travel-requests/${id}`)
      .then((r) => unwrapData<TravelRequest>(r)),

  store: (data: Partial<TravelRequest>) =>
    api
      .post<{ data: TravelRequest }>('/journey/travel-requests', data)
      .then((r) => unwrapData<TravelRequest>(r)),

  update: (id: number, data: Partial<TravelRequest>) =>
    api
      .put<{ data: TravelRequest }>(`/journey/travel-requests/${id}`, data)
      .then((r) => unwrapData<TravelRequest>(r)),

  approve: (id: number) =>
    api
      .post<{ data: TravelRequest }>(`/journey/travel-requests/${id}/approve`)
      .then((r) => unwrapData<TravelRequest>(r)),

  cancel: (id: number) =>
    api
      .post<{ data: TravelRequest }>(`/journey/travel-requests/${id}/cancel`)
      .then((r) => unwrapData<TravelRequest>(r)),

  delete: (id: number) =>
    api.delete(`/journey/travel-requests/${id}`),

  submitExpenseReport: (
    travelRequestId: number,
    items: Array<{ type: string; description: string; amount: number; expense_date: string; receipt_path?: string }>,
  ) =>
    api
      .post<{ data: TravelExpenseReport }>(`/journey/travel-requests/${travelRequestId}/expense-report`, { items })
      .then((r) => r.data?.data ?? r.data),

  approveExpenseReport: (travelRequestId: number) =>
    api
      .post<{ data: TravelExpenseReport }>(`/journey/travel-requests/${travelRequestId}/expense-report/approve`)
      .then((r) => r.data?.data ?? r.data),
}
