import api from './api'
import type { PaginatedResponse } from '@/types/api'

export interface TechnicianCertification {
  id: number
  user_id: number
  user?: { id: number; name: string }
  type: string
  name: string
  number: string | null
  issued_at: string
  expires_at: string | null
  issuer: string | null
  document_path: string | null
  status: 'valid' | 'expiring_soon' | 'expired' | 'revoked'
  required_for_service_types: string[] | null
  created_at: string
}

export interface EligibilityCheck {
  eligible: boolean
  blocking: Array<{ type: string; reason: string; message: string }>
}

export interface BiometricConsentStatus {
  has_consent: boolean
  consent: {
    id: number
    data_type: string
    legal_basis: string
    purpose: string
    consented_at: string
    revoked_at: string | null
    alternative_method: string | null
    is_active: boolean
  } | null
}

export const certificationApi = {
  list: (params?: { user_id?: number; type?: string; status?: string; per_page?: number }) =>
    api.get<PaginatedResponse<TechnicianCertification>>('/journey/certifications', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: TechnicianCertification }>(`/journey/certifications/${id}`).then((r) => r.data.data),

  store: (data: Partial<TechnicianCertification>) =>
    api.post<{ data: TechnicianCertification }>('/journey/certifications', data).then((r) => r.data.data),

  update: (id: number, data: Partial<TechnicianCertification>) =>
    api.put<{ data: TechnicianCertification }>(`/journey/certifications/${id}`, data).then((r) => r.data.data),

  delete: (id: number) => api.delete(`/journey/certifications/${id}`),

  expiring: (days?: number) =>
    api.get<{ data: TechnicianCertification[] }>('/journey/certifications/expiring', { params: { days } }).then((r) => r.data.data),

  checkEligibility: (userId: number, serviceType: string) =>
    api.post<{ data: EligibilityCheck }>('/journey/certifications/check-eligibility', { user_id: userId, service_type: serviceType }).then((r) => r.data.data),
}

export const biometricConsentApi = {
  list: (userId?: number) =>
    api.get<{ data: Record<string, BiometricConsentStatus> }>('/journey/biometric-consents', { params: { user_id: userId } }).then((r) => r.data.data),

  check: (userId: number, dataType: string) =>
    api.post<{ data: { has_consent: boolean; alternative_method: string | null } }>('/journey/biometric-consents/check', { user_id: userId, data_type: dataType }).then((r) => r.data.data),

  grant: (data: { user_id: number; data_type: string; legal_basis: string; purpose: string; alternative_method?: string; retention_days?: number }) =>
    api.post('/journey/biometric-consents/grant', data).then((r) => r.data),

  revoke: (userId: number, dataType: string) =>
    api.post('/journey/biometric-consents/revoke', { user_id: userId, data_type: dataType }).then((r) => r.data),
}
