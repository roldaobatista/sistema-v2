import api from './api'
import { unwrapData } from './api'
import type { Equipment } from '@/types/equipment'
import type {
  EquipmentAlert,
  EquipmentCalibration,
  EquipmentDashboardData,
  EquipmentDocument,
  EquipmentMaintenance,
  EquipmentQrResult,
} from '@/types/equipment'

interface ApiDataResponse<T> {
  data: T
}

interface PaginatedResponse<T> {
  data: T[]
  meta?: {
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
  }
  last_page?: number
}

interface EquipmentConstants {
  categories?: Record<string, string>
  precision_classes?: Record<string, string>
  statuses?: Record<string, string | { label: string; color: string }>
}

type EquipmentFilters = Record<string, string | number | boolean | undefined>

export const equipmentApi = {
  list: (params?: EquipmentFilters) =>
    api.get<PaginatedResponse<Equipment>>('/equipments', { params }).then(r => r.data),

  detail: (id: number) =>
    api.get<ApiDataResponse<Equipment>>(`/equipments/${id}`).then((response) => unwrapData<Equipment>(response)),

  dashboard: (): Promise<EquipmentDashboardData> =>
    api.get<ApiDataResponse<EquipmentDashboardData> | EquipmentDashboardData>('/equipments-dashboard').then(r => {
        const data = unwrapData(r)
        if (data && typeof data === 'object' && 'total' in data) return data as EquipmentDashboardData
        return {
          total: 0,
          overdue: 0,
          due_7_days: 0,
          due_30_days: 0,
          critical_count: 0,
          by_category: {},
          by_status: {},
        }
    }),

  constants: () =>
    api
      .get<ApiDataResponse<EquipmentConstants> | EquipmentConstants>('/equipments-constants')
      .then((response) => unwrapData<EquipmentConstants>(response)),

  alerts: () =>
    api.get<ApiDataResponse<{ alerts: EquipmentAlert[] }>>('/equipments-alerts').then((response) => unwrapData<{ alerts: EquipmentAlert[] }>(response)),

  create: (data: Record<string, unknown>) =>
    api.post<ApiDataResponse<Equipment>>('/equipments', data).then((response) => unwrapData<Equipment>(response)),

  update: (id: number, data: Record<string, unknown>) =>
    api.put<ApiDataResponse<Equipment>>(`/equipments/${id}`, data).then((response) => unwrapData<Equipment>(response)),

  destroy: (id: number) =>
    api.delete(`/equipments/${id}`),

  export: () =>
    api.get('/equipments-export', { responseType: 'blob' }),

  getCalibrationPdf: (equipmentId: number, calibrationId: number) =>
    api.get(`/equipments/${equipmentId}/calibrations/${calibrationId}/pdf`, { responseType: 'blob' }),

  generateQr: (id: number) =>
    api.post<ApiDataResponse<EquipmentQrResult>>(`/equipments/${id}/generate-qr`).then((response) => unwrapData<EquipmentQrResult>(response)),

  createCalibration: (equipmentId: number, payload: Record<string, unknown>) =>
    api
      .post<ApiDataResponse<{ calibration: EquipmentCalibration }>>(`/equipments/${equipmentId}/calibrations`, payload)
      .then((response) => unwrapData<{ calibration: EquipmentCalibration }>(response)),

  getDocumentDownload: (documentId: number) =>
    api.get(`/equipment-documents/${documentId}/download`, { responseType: 'blob' }),

  listMaintenances: (params?: EquipmentFilters) =>
    api.get<PaginatedResponse<EquipmentMaintenance>>('/equipment-maintenances', { params }).then(r => r.data),

  showMaintenance: (id: number) =>
    api.get<ApiDataResponse<EquipmentMaintenance>>(`/equipment-maintenances/${id}`).then((response) => unwrapData<EquipmentMaintenance>(response)),

  createMaintenance: (payload: Record<string, unknown>) =>
    api.post<ApiDataResponse<EquipmentMaintenance>>('/equipment-maintenances', payload).then((response) => unwrapData<EquipmentMaintenance>(response)),

  updateMaintenance: (id: number, payload: Record<string, unknown>) =>
    api.put(`/equipment-maintenances/${id}`, payload),

  deleteMaintenance: (id: number) =>
    api.delete(`/equipment-maintenances/${id}`),

  calibrationHistory: (equipmentId: number) =>
    api
      .get<ApiDataResponse<{ calibrations: EquipmentCalibration[] }>>(`/equipments/${equipmentId}/calibrations`)
      .then((response) => unwrapData<{ calibrations: EquipmentCalibration[] }>(response)),

  uploadDocument: (equipmentId: number, formData: FormData) =>
    api
      .post<ApiDataResponse<EquipmentDocument>>(`/equipments/${equipmentId}/documents`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((response) => unwrapData<EquipmentDocument>(response)),

  deleteDocument: (documentId: number) =>
    api.delete(`/equipment-documents/${documentId}`),

  // ISO 17025 §7.8.6 — Conformity decision rule evaluation (ILAC G8:09/2019)
  evaluateDecision: (calibrationId: number, payload: EvaluateDecisionPayload) =>
    api
      .post<ApiDataResponse<CalibrationDecisionResource>>(
        `/equipment-calibrations/${calibrationId}/evaluate-decision`,
        payload,
      )
      .then((response) => unwrapData<CalibrationDecisionResource>(response)),
}

export interface EvaluateDecisionPayload {
  rule: 'simple' | 'guard_band' | 'shared_risk'
  coverage_factor_k: number
  confidence_level?: number
  guard_band_mode?: 'k_times_u' | 'percent_limit' | 'fixed_abs' | null
  guard_band_value?: number | null
  producer_risk_alpha?: number | null
  consumer_risk_beta?: number | null
  notes?: string | null
}

export interface CalibrationDecisionResource {
  id: number
  decision: {
    rule: 'simple' | 'guard_band' | 'shared_risk' | null
    result: 'accept' | 'warn' | 'reject' | null
    coverage_factor_k: number | null
    confidence_level: number | null
    guard_band_mode: string | null
    guard_band_value: number | null
    guard_band_applied: number | null
    producer_risk_alpha: number | null
    consumer_risk_beta: number | null
    z_value: number | null
    false_accept_probability: number | null
    calculated_at: string | null
    calculated_by?: { id: number; name: string } | null
    notes: string | null
  }
}
