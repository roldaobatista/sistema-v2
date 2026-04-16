import { describe, expect, it } from 'vitest'
import {
  getStandardWeightStatusLabel,
  normalizeStandardWeightsPage,
  normalizeStandardWeightSummary,
} from './standard-weight-utils'

describe('standard-weight-utils', () => {
  it('resolve label from status map with object payload', () => {
    expect(getStandardWeightStatusLabel({
      active: { label: 'Ativo', color: 'green' },
    }, 'active')).toBe('Ativo')
  })

  it('fallback to raw status when map is missing', () => {
    expect(getStandardWeightStatusLabel(undefined, 'out_of_service')).toBe('out_of_service')
  })

  it('normalize paginated list defaults safely', () => {
    expect(normalizeStandardWeightsPage(undefined)).toEqual({
      weights: [],
      total: 0,
      lastPage: 1,
    })
  })

  it('normalize expiring summary defaults safely', () => {
    expect(normalizeStandardWeightSummary(undefined)).toEqual({
      expiring: [],
      expired: [],
      expiring_count: 0,
      expired_count: 0,
    })
  })
})
