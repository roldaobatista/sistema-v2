import { describe, expect, it } from 'vitest'
import { normalizeMaintenanceType, normalizePortalCalibrationStatus } from './equipment-utils'

describe('equipment-utils', () => {
  it('normaliza os status de calibracao do portal', () => {
    expect(normalizePortalCalibrationStatus('em_dia')).toBe('valid')
    expect(normalizePortalCalibrationStatus('vence_em_breve')).toBe('expiring')
    expect(normalizePortalCalibrationStatus('vencida')).toBe('expired')
    expect(normalizePortalCalibrationStatus('valid')).toBe('valid')
    expect(normalizePortalCalibrationStatus(null)).toBeNull()
  })

  it('normaliza tipos de manutencao legados do frontend', () => {
    expect(normalizeMaintenanceType('preventive')).toBe('preventiva')
    expect(normalizeMaintenanceType('corrective')).toBe('corretiva')
    expect(normalizeMaintenanceType('ajuste')).toBe('ajuste')
    expect(normalizeMaintenanceType(undefined)).toBe('')
  })
})
