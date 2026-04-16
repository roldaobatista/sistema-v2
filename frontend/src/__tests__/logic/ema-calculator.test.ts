import { describe, it, expect } from 'vitest'

// ── EMA Calculator Frontend (matching backend EmaCalculator exactly) ──

type AccuracyClass = 'I' | 'II' | 'III' | 'IIII'

const EMA_TABLE: Record<AccuracyClass, [number, number][]> = {
  I: [[50000, 0.5], [200000, 1.0], [Infinity, 1.5]],
  II: [[5000, 0.5], [20000, 1.0], [100000, 1.5]],
  III: [[500, 0.5], [2000, 1.0], [10000, 1.5]],
  IIII: [[50, 0.5], [200, 1.0], [1000, 1.5]],
}

function calculateEMA(
  accClass: AccuracyClass,
  eValue: number,
  loadValue: number,
  verificationType: 'initial' | 'subsequent' | 'in_use' = 'initial'
): number {
  if (eValue <= 0) throw new Error(`e must be > 0, got ${eValue}`)
  const table = EMA_TABLE[accClass]
  if (!table) throw new Error(`Unknown class: ${accClass}`)

  const multiplesOfE = loadValue / eValue
  let emaMultiple = 1.5
  for (const [threshold, mult] of table) {
    if (multiplesOfE <= threshold) {
      emaMultiple = mult
      break
    }
  }
  let ema = emaMultiple * eValue
  if (verificationType === 'in_use') ema *= 2
  return Math.round(ema * 1000000) / 1000000
}

function suggestPoints(
  accClass: AccuracyClass,
  eValue: number,
  maxCapacity: number,
  verificationType: 'initial' | 'in_use' = 'initial'
): { load: number; percentage: number; ema: number }[] {
  return [0, 25, 50, 75, 100].map(pct => {
    const load = Math.round((maxCapacity * pct) / 100 * 10000) / 10000
    return {
      load,
      percentage: pct,
      ema: pct === 0 ? 0 : calculateEMA(accClass, eValue, load, verificationType),
    }
  })
}

function isConforming(error: number, ema: number): boolean {
  return Math.abs(error) <= Math.abs(ema)
}

function suggestEccentricityLoad(maxCapacity: number): number {
  return Math.round((maxCapacity / 3) * 10000) / 10000
}

function suggestRepeatabilityLoad(maxCapacity: number): number {
  return Math.round(maxCapacity * 0.5 * 10000) / 10000
}

// ── Class III tests (typical commercial balance) ──

describe('EMA Class III', () => {
  it('200g load, e=1g → EMA 0.5g', () => expect(calculateEMA('III', 1, 200)).toBe(0.5))
  it('1000g load, e=1g → EMA 1.0g', () => expect(calculateEMA('III', 1, 1000)).toBe(1.0))
  it('5000g load, e=1g → EMA 1.5g', () => expect(calculateEMA('III', 1, 5000)).toBe(1.5))
  it('boundary 500e → EMA 0.5', () => expect(calculateEMA('III', 1, 500)).toBe(0.5))
  it('boundary 501e → EMA 1.0', () => expect(calculateEMA('III', 1, 501)).toBe(1.0))
  it('in_use doubles EMA', () => {
    expect(calculateEMA('III', 1, 200, 'in_use')).toBe(1.0)
  })
})

// ── Class I tests (analytical) ──

describe('EMA Class I', () => {
  it('10g load, e=0.001g → EMA 0.0005g', () => expect(calculateEMA('I', 0.001, 10)).toBe(0.0005))
  it('300g load, e=0.001g → EMA 0.0015g', () => expect(calculateEMA('I', 0.001, 300)).toBe(0.0015))
  it('100g load, e=0.001g → EMA 0.001g', () => expect(calculateEMA('I', 0.001, 100)).toBe(0.001))
})

// ── Class II tests ──

describe('EMA Class II', () => {
  it('10g load, e=0.01g → EMA 0.005g', () => expect(calculateEMA('II', 0.01, 10)).toBe(0.005))
  it('100g load, e=0.01g → EMA 0.01g', () => expect(calculateEMA('II', 0.01, 100)).toBe(0.01))
})

// ── Class IIII tests (road bridges) ──

describe('EMA Class IIII', () => {
  it('1000kg load, e=50kg → EMA 25kg', () => expect(calculateEMA('IIII', 50, 1000)).toBe(25))
  it('5000kg load, e=50kg → EMA 50kg', () => expect(calculateEMA('IIII', 50, 5000)).toBe(50))
})

// ── Invalid inputs ──

describe('EMA Invalid', () => {
  it('e=0 throws', () => expect(() => calculateEMA('III', 0, 100)).toThrow())
  it('negative e throws', () => expect(() => calculateEMA('III', -1, 100)).toThrow())
})

// ── suggestPoints ──

describe('suggestPoints', () => {
  it('returns 5 points', () => expect(suggestPoints('III', 1, 10000)).toHaveLength(5))
  it('first is 0%', () => {
    const points = suggestPoints('III', 1, 10000)
    expect(points[0].percentage).toBe(0)
    expect(points[0].ema).toBe(0)
  })
  it('last is 100%', () => {
    const points = suggestPoints('III', 1, 10000)
    expect(points[4].percentage).toBe(100)
    expect(points[4].load).toBe(10000)
  })
  it('50% of 10000 = 5000', () => {
    const points = suggestPoints('III', 1, 10000)
    expect(points[2].load).toBe(5000)
  })
})

// ── isConforming ──

describe('isConforming', () => {
  it('within EMA → conforming', () => expect(isConforming(0.3, 0.5)).toBe(true))
  it('at boundary → conforming', () => expect(isConforming(0.5, 0.5)).toBe(true))
  it('exceeds EMA → non-conforming', () => expect(isConforming(0.6, 0.5)).toBe(false))
  it('negative within → conforming', () => expect(isConforming(-0.3, 0.5)).toBe(true))
  it('negative exceeds → non-conforming', () => expect(isConforming(-0.6, 0.5)).toBe(false))
})

// ── Helper functions ──

describe('Suggest Loads', () => {
  it('eccentricity = 1/3 of max', () => expect(suggestEccentricityLoad(30000)).toBe(10000))
  it('repeatability = 50% of max', () => expect(suggestRepeatabilityLoad(10000)).toBe(5000))
  it('eccentricity precision', () => expect(suggestEccentricityLoad(100)).toBeCloseTo(33.3333, 3))
})

// ── Integration: full calibration workflow ──

describe('Full Calibration Workflow', () => {
  it('commercial balance III, e=5g, max=30kg', () => {
    const points = suggestPoints('III', 5, 30000)
    expect(points).toHaveLength(5)

    // 25% = 7500g → 1500 multiples → ≤2000 → EMA = 5g
    expect(points[1].ema).toBe(5)

    // 50% = 15000g → 3000 multiples → ≤10000 → EMA = 7.5g
    expect(points[2].ema).toBe(7.5)

    // eccentricity
    expect(suggestEccentricityLoad(30000)).toBe(10000)

    // conformity check
    expect(isConforming(4.5, 5)).toBe(true)
    expect(isConforming(5.5, 5)).toBe(false)
  })
})
