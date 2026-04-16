import { describe, it, expect } from 'vitest'
import { renderHook } from '@testing-library/react'
import {
    calculateEma,
    calculateEmaForPoints,
    calculateRepeatability,
    suggestPoints,
    suggestEccentricityLoad,
    suggestRepeatabilityLoad,
    isConforming,
    calculateReadingError,
    calculateExpandedUncertainty,
    useCalibrationCalculations,
} from '@/hooks/useCalibrationCalculations'

describe('calculateEma', () => {
    it('should return 0 when eValue is 0', () => {
        expect(calculateEma('III', 0, 1000)).toBe(0)
    })

    it('should return 0 when eValue is negative', () => {
        expect(calculateEma('III', -1, 1000)).toBe(0)
    })

    it('should calculate EMA for class III with low multiples of e', () => {
        // load=100, e=1 -> multiplesOfE=100 -> <=500 -> multiple=0.5
        // EMA = 0.5 * 1 = 0.5
        const result = calculateEma('III', 1, 100)
        expect(result).toBe(0.5)
    })

    it('should calculate EMA for class III with medium multiples of e', () => {
        // load=1000, e=1 -> multiplesOfE=1000 -> <=2000 -> multiple=1.0
        // EMA = 1.0 * 1 = 1.0
        const result = calculateEma('III', 1, 1000)
        expect(result).toBe(1)
    })

    it('should calculate EMA for class III with high multiples of e', () => {
        // load=5000, e=1 -> multiplesOfE=5000 -> <=10000 -> multiple=1.5
        // EMA = 1.5 * 1 = 1.5
        const result = calculateEma('III', 1, 5000)
        expect(result).toBe(1.5)
    })

    it('should double EMA for in_use verification type', () => {
        const initial = calculateEma('III', 1, 100, 'initial')
        const inUse = calculateEma('III', 1, 100, 'in_use')
        expect(inUse).toBe(initial * 2)
    })

    it('should calculate same EMA for initial and subsequent verification', () => {
        const initial = calculateEma('III', 1, 100, 'initial')
        const subsequent = calculateEma('III', 1, 100, 'subsequent')
        expect(initial).toBe(subsequent)
    })

    it('should return 1.5 for unknown precision class', () => {
        // Unknown class -> findEmaMultiple returns 1.5
        const result = calculateEma('UNKNOWN', 1, 100)
        expect(result).toBe(1.5)
    })

    it('should handle class I precision', () => {
        // load=10000, e=1 -> multiplesOfE=10000 -> <=50000 -> multiple=0.5
        const result = calculateEma('I', 1, 10000)
        expect(result).toBe(0.5)
    })
})

describe('calculateEmaForPoints', () => {
    it('should return EMA results for multiple load values', () => {
        const results = calculateEmaForPoints('III', 1, [0, 250, 500, 750, 1000])
        expect(results).toHaveLength(5)
        results.forEach((r) => {
            expect(r).toHaveProperty('load')
            expect(r).toHaveProperty('ema')
            expect(r).toHaveProperty('multiplesOfE')
        })
    })

    it('should calculate correct multiplesOfE', () => {
        const results = calculateEmaForPoints('III', 2, [100])
        expect(results[0].multiplesOfE).toBe(50)
    })

    it('should handle empty load values array', () => {
        const results = calculateEmaForPoints('III', 1, [])
        expect(results).toHaveLength(0)
    })

    it('should return multiplesOfE as 0 when eValue is 0', () => {
        const results = calculateEmaForPoints('III', 0, [100])
        expect(results[0].multiplesOfE).toBe(0)
    })
})

describe('calculateRepeatability', () => {
    it('should return zeros for less than 2 measurements', () => {
        const result = calculateRepeatability([5])
        expect(result.mean).toBe(0)
        expect(result.stdDev).toBe(0)
        expect(result.uncertaintyTypeA).toBe(0)
    })

    it('should return zeros for empty array', () => {
        const result = calculateRepeatability([])
        expect(result.mean).toBe(0)
        expect(result.stdDev).toBe(0)
    })

    it('should calculate correct mean', () => {
        const result = calculateRepeatability([10, 20, 30])
        expect(result.mean).toBe(20)
    })

    it('should calculate standard deviation for varied values', () => {
        const result = calculateRepeatability([10, 20, 30])
        expect(result.stdDev).toBeGreaterThan(0)
    })

    it('should return stdDev 0 when all values are the same', () => {
        const result = calculateRepeatability([5, 5, 5, 5])
        expect(result.stdDev).toBe(0)
    })

    it('should calculate uncertaintyTypeA as stdDev / sqrt(n)', () => {
        const result = calculateRepeatability([10, 20, 30, 40])
        const expectedStdDev = Math.sqrt(
            ([10, 20, 30, 40].reduce((s, v) => s + (v - 25) ** 2, 0)) / 3,
        )
        const expectedUA = expectedStdDev / Math.sqrt(4)
        expect(result.uncertaintyTypeA).toBeCloseTo(expectedUA, 4)
    })

    it('should filter out null and NaN values', () => {
        const result = calculateRepeatability([10, NaN, 20, null as any, 30])
        expect(result.measurements).toHaveLength(3)
        expect(result.mean).toBe(20)
    })

    it('should handle large differences in values', () => {
        const result = calculateRepeatability([1, 1000000])
        expect(result.stdDev).toBeGreaterThan(0)
        expect(result.mean).toBeCloseTo(500000.5, 1)
    })
})

describe('suggestPoints', () => {
    it('should return 5 points at 0%, 25%, 50%, 75%, 100%', () => {
        const points = suggestPoints(1000)
        expect(points).toEqual([0, 250, 500, 750, 1000])
    })

    it('should handle decimal max capacity', () => {
        const points = suggestPoints(100)
        expect(points).toHaveLength(5)
        expect(points[0]).toBe(0)
        expect(points[4]).toBe(100)
    })
})

describe('suggestEccentricityLoad', () => {
    it('should return ~1/3 of max capacity', () => {
        const load = suggestEccentricityLoad(300)
        expect(load).toBe(100)
    })
})

describe('suggestRepeatabilityLoad', () => {
    it('should return 50% of max capacity', () => {
        const load = suggestRepeatabilityLoad(1000)
        expect(load).toBe(500)
    })
})

describe('isConforming', () => {
    it('should return true when error is within EMA', () => {
        expect(isConforming(0.3, 0.5)).toBe(true)
    })

    it('should return true when error equals EMA', () => {
        expect(isConforming(0.5, 0.5)).toBe(true)
    })

    it('should return false when error exceeds EMA', () => {
        expect(isConforming(0.6, 0.5)).toBe(false)
    })

    it('should handle negative errors', () => {
        expect(isConforming(-0.3, 0.5)).toBe(true)
        expect(isConforming(-0.6, 0.5)).toBe(false)
    })
})

describe('calculateReadingError', () => {
    it('should return difference between indication and reference', () => {
        expect(calculateReadingError(100.5, 100)).toBe(0.5)
    })

    it('should return negative error when indication is less than reference', () => {
        expect(calculateReadingError(99.5, 100)).toBe(-0.5)
    })

    it('should return 0 when values match', () => {
        expect(calculateReadingError(100, 100)).toBe(0)
    })
})

describe('calculateExpandedUncertainty', () => {
    it('should calculate U = k * sqrt(uA^2 + uB^2) with default k=2', () => {
        const result = calculateExpandedUncertainty(3, 4)
        // uc = sqrt(9+16) = 5; U = 2*5 = 10
        expect(result).toBe(10)
    })

    it('should use default uncertaintyTypeB=0', () => {
        const result = calculateExpandedUncertainty(5)
        // uc = sqrt(25+0) = 5; U = 2*5 = 10
        expect(result).toBe(10)
    })

    it('should use custom k factor', () => {
        const result = calculateExpandedUncertainty(3, 4, 3)
        // uc = 5; U = 3*5 = 15
        expect(result).toBe(15)
    })
})

describe('useCalibrationCalculations hook', () => {
    it('should return suggestedPoints based on maxCapacity', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        expect(result.current.suggestedPoints).toEqual([0, 250, 500, 750, 1000])
    })

    it('should return emaResults for each suggested point', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        expect(result.current.emaResults).toHaveLength(5)
    })

    it('should return eccentricityLoad as ~1/3 of maxCapacity', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 300 }),
        )
        expect(result.current.eccentricityLoad).toBe(100)
    })

    it('should return repeatabilityLoad as 50% of maxCapacity', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        expect(result.current.repeatabilityLoad).toBe(500)
    })

    it('should provide calculateEmaForLoad function', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        const ema = result.current.calculateEmaForLoad(500)
        expect(ema).toBe(0.5)
    })

    it('should provide checkConformity function', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        const conforming = result.current.checkConformity(0.3, 100)
        expect(conforming).toHaveProperty('ema')
        expect(conforming).toHaveProperty('conforming')
        expect(conforming.conforming).toBe(true)
    })

    it('should include procedureConfig with class info', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        expect(result.current.procedureConfig.minWeightClass).toBe('III')
        expect(result.current.procedureConfig.classLabel).toBe('Classe III')
    })

    it('should expose calculateRepeatability and calculateExpandedUncertainty', () => {
        const { result } = renderHook(() =>
            useCalibrationCalculations({ precisionClass: 'III', eValue: 1, maxCapacity: 1000 }),
        )
        expect(typeof result.current.calculateRepeatability).toBe('function')
        expect(typeof result.current.calculateExpandedUncertainty).toBe('function')
    })
})
