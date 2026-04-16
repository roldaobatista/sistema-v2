export type PortalCalibrationStatus = 'valid' | 'expiring' | 'expired' | null

export function normalizePortalCalibrationStatus(status: string | null | undefined): PortalCalibrationStatus {
  if (!status) {
    return null
  }

  if (status === 'valid' || status === 'em_dia') {
    return 'valid'
  }

  if (status === 'expiring' || status === 'vence_em_breve') {
    return 'expiring'
  }

  if (status === 'expired' || status === 'vencida') {
    return 'expired'
  }

  return null
}

export function normalizeMaintenanceType(type: string | null | undefined): string {
  if (!type) {
    return ''
  }

  const normalized = type.trim().toLowerCase()

  if (normalized === 'preventive') {
    return 'preventiva'
  }

  if (normalized === 'corrective') {
    return 'corretiva'
  }

  return normalized
}
