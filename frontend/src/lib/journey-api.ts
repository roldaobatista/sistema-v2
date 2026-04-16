import api, { unwrapData } from './api'
import type { PaginatedResponse } from '@/types/api'
import type {
  JourneyDay,
  JourneyDayFilters,
  JourneyBlock,
  JourneyPolicy,
} from '@/types/journey'

export const journeyApi = {
  // Journey Days
  listDays: (filters?: JourneyDayFilters) =>
    api
      .get<PaginatedResponse<JourneyDay>>('/journey/days', { params: filters })
      .then((r) => r.data),

  showDay: (id: number) =>
    api
      .get<{ data: JourneyDay }>(`/journey/days/${id}`)
      .then((r) => unwrapData<JourneyDay>(r)),

  reclassifyDay: (id: number) =>
    api
      .post<{ data: JourneyDay }>(`/journey/days/${id}/reclassify`)
      .then((r) => unwrapData<JourneyDay>(r)),

  // Journey Blocks
  adjustBlock: (
    blockId: number,
    data: {
      classification: string
      started_at: string
      ended_at?: string | null
      adjustment_reason: string
    },
  ) =>
    api
      .post<{ data: JourneyBlock }>(`/journey/blocks/${blockId}/adjust`, data)
      .then((r) => unwrapData<JourneyBlock>(r)),

  // Journey Policies
  listPolicies: (params?: { per_page?: number }) =>
    api
      .get<PaginatedResponse<JourneyPolicy>>('/journey/policies', { params })
      .then((r) => r.data),

  showPolicy: (id: number) =>
    api
      .get<{ data: JourneyPolicy }>(`/journey/policies/${id}`)
      .then((r) => unwrapData<JourneyPolicy>(r)),

  storePolicy: (data: Partial<JourneyPolicy>) =>
    api
      .post<{ data: JourneyPolicy }>('/journey/policies', data)
      .then((r) => unwrapData<JourneyPolicy>(r)),

  updatePolicy: (id: number, data: Partial<JourneyPolicy>) =>
    api
      .put<{ data: JourneyPolicy }>(`/journey/policies/${id}`, data)
      .then((r) => unwrapData<JourneyPolicy>(r)),

  deletePolicy: (id: number) => api.delete(`/journey/policies/${id}`),
}
