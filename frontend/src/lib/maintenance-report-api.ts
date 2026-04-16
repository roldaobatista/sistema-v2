import api, { unwrapData } from './api'
import type { MaintenanceReport } from '@/types/work-order'

export interface MaintenanceReportListParams {
  work_order_id?: number
  equipment_id?: number
  per_page?: number
}

export interface MaintenanceReportPayload {
  work_order_id: number
  equipment_id: number
  defect_found: string
  probable_cause?: string | null
  corrective_action?: string | null
  parts_replaced?: { name: string; part_number?: string | null; origin?: string | null; quantity?: number }[] | null
  seal_status?: string | null
  new_seal_number?: string | null
  condition_before: string
  condition_after: string
  requires_calibration_after?: boolean
  requires_ipem_verification?: boolean
  started_at?: string | null
  completed_at?: string | null
  notes?: string | null
}

export const maintenanceReportApi = {
  list: (params?: MaintenanceReportListParams) =>
    api.get<{ data: MaintenanceReport[] }>('/maintenance-reports', { params }),

  detail: (id: number) =>
    api.get<{ data: MaintenanceReport }>(`/maintenance-reports/${id}`).then((response) => unwrapData<MaintenanceReport>(response)),

  create: (data: MaintenanceReportPayload) =>
    api.post<{ data: MaintenanceReport }>('/maintenance-reports', data),

  update: (id: number, data: Partial<MaintenanceReportPayload>) =>
    api.put<{ data: MaintenanceReport }>(`/maintenance-reports/${id}`, data),

  approve: (id: number) =>
    api.post<{ data: MaintenanceReport }>(`/maintenance-reports/${id}/approve`),

  destroy: (id: number) =>
    api.delete(`/maintenance-reports/${id}`),
}
