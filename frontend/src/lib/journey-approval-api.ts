import api, { unwrapData } from './api'
import type { PaginatedResponse } from '@/types/api'
import type { JourneyDay } from '@/types/journey'

export const journeyApprovalApi = {
  listPending: (level: 'operational' | 'hr', params?: { per_page?: number }) =>
    api
      .get<PaginatedResponse<JourneyDay>>(`/journey/approvals/${level}/pending`, { params })
      .then((r) => r.data),

  submitForApproval: (journeyDayId: number) =>
    api
      .post<{ data: JourneyDay }>(`/journey/days/${journeyDayId}/submit-approval`)
      .then((r) => unwrapData<JourneyDay>(r)),

  approve: (journeyDayId: number, level: 'operational' | 'hr', notes?: string) =>
    api
      .post<{ data: JourneyDay }>(`/journey/days/${journeyDayId}/approve/${level}`, { notes })
      .then((r) => unwrapData<JourneyDay>(r)),

  reject: (journeyDayId: number, level: 'operational' | 'hr', reason: string) =>
    api
      .post<{ data: JourneyDay }>(`/journey/days/${journeyDayId}/reject/${level}`, { reason })
      .then((r) => unwrapData<JourneyDay>(r)),
}
